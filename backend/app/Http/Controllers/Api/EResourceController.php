<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\EResource;
use App\Models\EResourceDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EResourceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = EResource::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('authors', 'like', "%{$term}%")
                  ->orWhere('subject_area', 'like', "%{$term}%")
                  ->orWhere('isbn', 'like', "%{$term}%")
                  ->orWhere('call_number', 'like', "%{$term}%");
            });
        }

        if ($request->filled('format')) {
            $query->where('format', $request->format);
        }

        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        if ($request->filled('subject_area')) {
            $query->where('subject_area', 'like', '%' . $request->subject_area . '%');
        }

        if ($request->filled('file_type')) {
            $query->where('file_type', $request->file_type);
        }

        return response()->json(
            $query->orderBy('title')->paginate(20)
        );
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total'         => EResource::count(),
            'storage_bytes' => (int) EResource::sum('file_size'),
            'downloads_30d' => EResourceDownload::where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    public function show(EResource $eResource): JsonResponse
    {
        return response()->json($eResource);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'authors'        => ['nullable', 'string', 'max:255'],
            'publisher'      => ['nullable', 'string', 'max:255'],
            'year'           => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'isbn'           => ['nullable', 'string', 'max:20'],
            'edition'        => ['nullable', 'string', 'max:50'],
            'description'    => ['nullable', 'string'],
            'call_number'    => ['nullable', 'string', 'max:50'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'subject_area'   => ['required', 'string', 'max:255'],
            'language'       => ['nullable', 'string', 'max:50'],
            'format'         => ['nullable', 'string', 'max:50'],
            'cover_image'    => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'file'           => ['required', 'file', 'max:1048576'], // 1 GB
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('e-resource-covers', 'public');
        }

        $uploaded   = $request->file('file');
        $storedPath = $uploaded->store('e-resources', 's3');

        if ($storedPath === false) {
            if (! empty($data['cover_image'])) {
                Storage::disk('public')->delete($data['cover_image']);
            }

            return response()->json([
                'message' => 'Failed to upload the document file to storage. Please try again.',
            ], 502);
        }

        $data['file_path'] = $storedPath;
        $data['file_size'] = $uploaded->getSize();
        $data['file_type'] = strtolower($uploaded->getClientOriginalExtension());

        unset($data['file']);

        return response()->json(EResource::create($data), 201);
    }

    public function update(Request $request, EResource $eResource): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'authors'        => ['nullable', 'string', 'max:255'],
            'publisher'      => ['nullable', 'string', 'max:255'],
            'year'           => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'isbn'           => ['nullable', 'string', 'max:20'],
            'edition'        => ['nullable', 'string', 'max:50'],
            'description'    => ['nullable', 'string'],
            'call_number'    => ['nullable', 'string', 'max:50'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'subject_area'   => ['sometimes', 'string', 'max:255'],
            'language'       => ['nullable', 'string', 'max:50'],
            'format'         => ['nullable', 'string', 'max:50'],
            'cover_image'    => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'file'           => ['nullable', 'file', 'max:1048576'], // 1 GB
        ]);

        if ($request->hasFile('cover_image')) {
            if ($eResource->cover_image) {
                Storage::disk('public')->delete($eResource->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('e-resource-covers', 'public');
        }

        if ($request->hasFile('file')) {
            $uploaded   = $request->file('file');
            $storedPath = $uploaded->store('e-resources', 's3');

            if ($storedPath === false) {
                return response()->json([
                    'message' => 'Failed to upload the document file to storage. Please try again.',
                ], 502);
            }

            if ($eResource->file_path) {
                Storage::disk('s3')->delete($eResource->file_path);
            }

            $data['file_path'] = $storedPath;
            $data['file_size'] = $uploaded->getSize();
            $data['file_type'] = strtolower($uploaded->getClientOriginalExtension());
        }

        unset($data['file']);

        $eResource->update($data);

        return response()->json($eResource->fresh());
    }

    public function destroy(EResource $eResource): JsonResponse
    {
        if ($eResource->cover_image) {
            Storage::disk('public')->delete($eResource->cover_image);
        }

        if ($eResource->file_path) {
            Storage::disk('s3')->delete($eResource->file_path);
        }

        $eResource->delete();

        return response()->json(['message' => 'E-Resource deleted successfully.']);
    }

    public function read(EResource $eResource): StreamedResponse|JsonResponse
    {
        if (! $eResource->file_path || ! Storage::disk('s3')->exists($eResource->file_path)) {
            return response()->json(['message' => 'No file available for this resource.'], 404);
        }

        $ext = strtolower($eResource->file_type ?? pathinfo($eResource->file_path, PATHINFO_EXTENSION));

        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'epub' => 'application/epub+zip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };

        return Storage::disk('s3')->response(
            $eResource->file_path,
            $eResource->title . '.' . $ext,
            ['Content-Type' => $mime],
            'inline'
        );
    }

    /**
     * Return the file as base64 inside a JSON envelope. Because the response is
     * application/json (not application/pdf), download managers such as IDM do
     * not intercept it — the browser-side reader decodes it to a blob locally.
     */
    public function inline(EResource $eResource): JsonResponse
    {
        if (! $eResource->file_path || ! Storage::disk('s3')->exists($eResource->file_path)) {
            return response()->json(['message' => 'No file available for this resource.'], 404);
        }

        $ext = strtolower($eResource->file_type ?? pathinfo($eResource->file_path, PATHINFO_EXTENSION));

        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'epub' => 'application/epub+zip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };

        return response()->json([
            'mime' => $mime,
            'name' => $eResource->title . '.' . $ext,
            'data' => base64_encode(Storage::disk('s3')->get($eResource->file_path)),
        ]);
    }

    public function download(EResource $eResource): StreamedResponse|JsonResponse
    {
        if (! $eResource->file_path || ! Storage::disk('s3')->exists($eResource->file_path)) {
            return response()->json(['message' => 'No file available for this resource.'], 404);
        }

        EResourceDownload::create([
            'e_resource_id' => $eResource->id,
            'user_id'       => auth()->id(),
        ]);

        $ext = $eResource->file_type
            ?? pathinfo($eResource->file_path, PATHINFO_EXTENSION);

        return Storage::disk('s3')->download(
            $eResource->file_path,
            $eResource->title . '.' . $ext
        );
    }
}
