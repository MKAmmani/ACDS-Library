<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Media;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class MediaController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 50), 200);

        $query = Media::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('kind', 'like', "%{$term}%")
                  ->orWhere('tag', 'like', "%{$term}%");
            });
        }

        if ($request->filled('kind')) {
            $query->where('kind', $request->kind);
        }

        $total = (clone $query)->count();

        $media = $query
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json([
            'data'        => $media,
            'total'       => $total,
            'total_views' => (int) Media::sum('views'),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        // Large video/audio uploads stream to R2 over HTTP and can take longer
        // than PHP's default 30s script timeout, which kills the request with
        // a fatal error before the CORS/response headers are ever sent (shows
        // up in the browser as a misleading CORS error). Give uploads room.
        set_time_limit(600);

        $this->nullifyEmptyDate($request);

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'kind'           => ['nullable', 'string', 'max:50'],
            'tag'            => ['nullable', 'string', 'max:100'],
            'duration_label' => ['nullable', 'string', 'max:20'],
            'thumbnail_url'  => ['nullable', 'string', 'max:2048'],
            'thumbnail'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'media_url'      => ['nullable', 'string', 'max:2048'],
            'media'          => ['nullable', 'file', 'mimes:mp4,webm,mov,mkv,avi,ogg,mp3,wav,m4a,aac,flac,oga,opus', 'max:1048576'], // 1 GB — video or audio
            'views'          => ['nullable', 'integer', 'min:0'],
            'published_at'   => ['nullable', 'date'],
        ]);

        unset($data['thumbnail'], $data['media']);

        if ($request->hasFile('thumbnail')) {
            $data['thumbnail']     = $request->file('thumbnail')->store('media-thumbnails', 'public');
            $data['thumbnail_url'] = null;
        }

        if ($request->hasFile('media')) {
            $uploaded            = $request->file('media');
            $data['media_path']  = $uploaded->store('media', 's3');
            $data['file_size']   = $uploaded->getSize();
            $data['mime_type']   = $uploaded->getMimeType();
            $data['media_url']   = null;
        }

        $data['published_at'] = $data['published_at'] ?? now();

        return response()->json(Media::create($data), 201);
    }

    public function update(Request $request, Media $medium): JsonResponse
    {
        // See store() — replacing the media file re-uploads to R2 and can
        // exceed PHP's default 30s execution limit on a slow connection.
        set_time_limit(600);

        $this->nullifyEmptyDate($request);

        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'kind'           => ['nullable', 'string', 'max:50'],
            'tag'            => ['nullable', 'string', 'max:100'],
            'duration_label' => ['nullable', 'string', 'max:20'],
            'thumbnail_url'  => ['nullable', 'string', 'max:2048'],
            'thumbnail'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'media_url'      => ['nullable', 'string', 'max:2048'],
            'media'          => ['nullable', 'file', 'mimes:mp4,webm,mov,mkv,avi,ogg,mp3,wav,m4a,aac,flac,oga,opus', 'max:1048576'], // 1 GB — video or audio
            'views'          => ['nullable', 'integer', 'min:0'],
            'published_at'   => ['nullable', 'date'],
        ]);

        unset($data['thumbnail'], $data['media']);

        // A blank date field means "leave it as it is", not "clear it".
        if (array_key_exists('published_at', $data) && is_null($data['published_at'])) {
            unset($data['published_at']);
        }

        if ($request->hasFile('thumbnail')) {
            if ($medium->thumbnail) {
                Storage::disk('public')->delete($medium->thumbnail);
            }
            $data['thumbnail']     = $request->file('thumbnail')->store('media-thumbnails', 'public');
            $data['thumbnail_url'] = null;
        } elseif ($request->filled('thumbnail_url') && $medium->thumbnail) {
            // Switching from an uploaded thumbnail back to a pasted URL.
            Storage::disk('public')->delete($medium->thumbnail);
            $data['thumbnail'] = null;
        }

        if ($request->hasFile('media')) {
            if ($medium->media_path) {
                Storage::disk('s3')->delete($medium->media_path);
            }
            $uploaded           = $request->file('media');
            $data['media_path'] = $uploaded->store('media', 's3');
            $data['file_size']  = $uploaded->getSize();
            $data['mime_type']  = $uploaded->getMimeType();
            $data['media_url']  = null;
        } elseif ($request->filled('media_url') && $medium->media_path) {
            // Switching from an uploaded file back to a pasted link.
            Storage::disk('s3')->delete($medium->media_path);
            $data['media_path'] = null;
            $data['file_size']  = null;
            $data['mime_type']  = null;
        }

        $medium->update($data);

        return response()->json($medium->fresh());
    }

    public function destroy(Media $medium): JsonResponse
    {
        if ($medium->thumbnail) {
            Storage::disk('public')->delete($medium->thumbnail);
        }

        if ($medium->media_path) {
            Storage::disk('s3')->delete($medium->media_path);
        }

        $medium->delete();

        return response()->json(['message' => 'Media item deleted successfully.']);
    }

    /**
     * Stream an uploaded video/audio file for inline playback. Public — the
     * media library is open content, same as the rest of the landing page.
     */
    public function stream(Media $medium): RedirectResponse|JsonResponse
    {
        if (! $medium->media_path || ! Storage::disk('s3')->exists($medium->media_path)) {
            return response()->json(['message' => 'No media file available for this item.'], 404);
        }

        $ext = strtolower(pathinfo($medium->media_path, PATHINFO_EXTENSION));

        // Prefer the MIME type detected at upload time (correctly distinguishes
        // e.g. audio/mpeg from video/mp4). Rows uploaded before that column
        // existed fall back to a guess from the file extension.
        $mime = $medium->mime_type ?: match ($ext) {
            'mp4'  => 'video/mp4',
            'webm' => 'video/webm',
            'mov'  => 'video/quicktime',
            'mkv'  => 'video/x-matroska',
            'avi'  => 'video/x-msvideo',
            'ogg'  => 'video/ogg',
            'mp3'  => 'audio/mpeg',
            'wav'  => 'audio/wav',
            'm4a'  => 'audio/mp4',
            'aac'  => 'audio/aac',
            'flac' => 'audio/flac',
            'oga'  => 'audio/ogg',
            'opus' => 'audio/opus',
            default => 'application/octet-stream',
        };

        // Redirect to a short-lived signed R2 URL instead of proxying the
        // bytes through PHP. Storage::response() ignores the Range header
        // entirely and always streams the whole file from byte 0 — that's
        // why rewinding/scrubbing on uploaded files was unreliable in the
        // player. R2 (S3-compatible) natively serves Range requests, so
        // sending the browser straight there gives the <video>/<audio>
        // element proper seeking, same as any normal player.
        $url = Storage::disk('s3')->temporaryUrl($medium->media_path, now()->addHours(2), [
            'ResponseContentType'        => $mime,
            'ResponseContentDisposition' => 'inline; filename="' . addslashes($medium->title . '.' . $ext) . '"',
        ]);

        return redirect()->away($url);
    }

    /**
     * An empty date input submits as "" rather than being omitted — the
     * 'nullable' rule only tolerates an actual null, so an empty string
     * would otherwise fail the 'date' rule. Treat "left blank" as "not sent".
     */
    private function nullifyEmptyDate(Request $request): void
    {
        if ($request->input('published_at') === '') {
            $request->merge(['published_at' => null]);
        }
    }
}
