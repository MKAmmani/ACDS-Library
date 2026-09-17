<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Journal;
use App\Models\JournalDownload;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class JournalController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Journal::query()->with('articles');

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('publisher_authors', 'like', "%{$term}%")
                  ->orWhere('issn', 'like', "%{$term}%")
                  ->orWhere('subject', 'like', "%{$term}%")
                  ->orWhere('call_number', 'like', "%{$term}%");
            });
        }

        if ($request->filled('subject')) {
            $query->where('subject', 'like', '%' . $request->subject . '%');
        }

        return response()->json(
            $query->orderBy('title')->paginate(20)
        );
    }

    public function stats(): JsonResponse
    {
        return response()->json([
            'total'         => Journal::count(),
            'storage_bytes' => (int) Journal::sum('file_size'),
            'downloads_30d' => JournalDownload::where('created_at', '>=', now()->subDays(30))->count(),
        ]);
    }

    public function show(Journal $journal): JsonResponse
    {
        return response()->json($journal->load('articles'));
    }

    /**
     * Decode the JSON-encoded `articles` field (sent this way because the
     * request is multipart/form-data, which can't carry a native nested
     * array of objects) and merge it back into the request as a real array
     * so it can be validated with the usual `articles.*.field` rules.
     */
    private function decodeArticles(Request $request): void
    {
        $raw = $request->input('articles');
        $decoded = is_string($raw) ? json_decode($raw, true) : null;
        $request->merge(['articles' => is_array($decoded) ? $decoded : []]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->decodeArticles($request);

        $data = $request->validate([
            'title'                    => ['required', 'string', 'max:255'],
            'publisher_authors'        => ['nullable', 'string', 'max:255'],
            'issn'                     => ['nullable', 'string', 'max:20'],
            'year'                     => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'call_number'              => ['nullable', 'string', 'max:50'],
            'subject'                  => ['nullable', 'string', 'max:255'],
            'shelf_location'           => ['nullable', 'string', 'max:100'],
            'cover_image'              => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'file'                     => ['required', 'file', 'max:1048576'], // 1 GB
            'articles'                 => ['array'],
            'articles.*.title'         => ['required', 'string', 'max:255'],
            'articles.*.authors'       => ['nullable', 'string', 'max:255'],
            'articles.*.page_range'    => ['nullable', 'string', 'max:50'],
        ]);

        $articles = $data['articles'] ?? [];
        unset($data['articles'], $data['file']);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('journal-covers', 'public');
        }

        $uploaded  = $request->file('file');
        $storedPath = $uploaded->store('journals', 's3');

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

        $journal = Journal::create($data);

        foreach ($articles as $i => $article) {
            $journal->articles()->create([
                'title'      => $article['title'],
                'authors'    => $article['authors'] ?? null,
                'page_range' => $article['page_range'] ?? null,
                'position'   => $i,
            ]);
        }

        return response()->json($journal->fresh('articles'), 201);
    }

    public function update(Request $request, Journal $journal): JsonResponse
    {
        $this->decodeArticles($request);

        $data = $request->validate([
            'title'                    => ['sometimes', 'string', 'max:255'],
            'publisher_authors'        => ['nullable', 'string', 'max:255'],
            'issn'                     => ['nullable', 'string', 'max:20'],
            'year'                     => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'call_number'              => ['nullable', 'string', 'max:50'],
            'subject'                  => ['nullable', 'string', 'max:255'],
            'shelf_location'           => ['nullable', 'string', 'max:100'],
            'cover_image'              => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'file'                     => ['nullable', 'file', 'max:1048576'], // 1 GB
            'articles'                 => ['array'],
            'articles.*.title'         => ['required', 'string', 'max:255'],
            'articles.*.authors'       => ['nullable', 'string', 'max:255'],
            'articles.*.page_range'    => ['nullable', 'string', 'max:50'],
        ]);

        $articles = $data['articles'] ?? [];
        unset($data['articles'], $data['file']);

        if ($request->hasFile('cover_image')) {
            if ($journal->cover_image) {
                Storage::disk('public')->delete($journal->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('journal-covers', 'public');
        }

        if ($request->hasFile('file')) {
            $uploaded   = $request->file('file');
            $storedPath = $uploaded->store('journals', 's3');

            if ($storedPath === false) {
                return response()->json([
                    'message' => 'Failed to upload the document file to storage. Please try again.',
                ], 502);
            }

            if ($journal->file_path && $journal->file_path !== '0') {
                Storage::disk('s3')->delete($journal->file_path);
            }

            $data['file_path'] = $storedPath;
            $data['file_size'] = $uploaded->getSize();
            $data['file_type'] = strtolower($uploaded->getClientOriginalExtension());
        }

        $journal->update($data);

        if ($request->has('articles')) {
            $journal->articles()->delete();
            foreach ($articles as $i => $article) {
                $journal->articles()->create([
                    'title'      => $article['title'],
                    'authors'    => $article['authors'] ?? null,
                    'page_range' => $article['page_range'] ?? null,
                    'position'   => $i,
                ]);
            }
        }

        return response()->json($journal->fresh('articles'));
    }

    public function destroy(Journal $journal): JsonResponse
    {
        if ($journal->cover_image) {
            Storage::disk('public')->delete($journal->cover_image);
        }

        if ($journal->file_path) {
            Storage::disk('s3')->delete($journal->file_path);
        }

        $journal->delete();

        return response()->json(['message' => 'Journal deleted successfully.']);
    }

    public function read(Journal $journal): StreamedResponse|JsonResponse
    {
        if (! $journal->file_path || ! Storage::disk('s3')->exists($journal->file_path)) {
            return response()->json(['message' => 'No file available for this journal.'], 404);
        }

        $ext = strtolower($journal->file_type ?? pathinfo($journal->file_path, PATHINFO_EXTENSION));

        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'epub' => 'application/epub+zip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };

        return Storage::disk('s3')->response(
            $journal->file_path,
            $journal->title . '.' . $ext,
            ['Content-Type' => $mime],
            'inline'
        );
    }

    /**
     * Return the file as base64 inside a JSON envelope. Because the response is
     * application/json (not application/pdf), download managers such as IDM do
     * not intercept it — the browser-side reader decodes it to a blob locally.
     */
    public function inline(Journal $journal): JsonResponse
    {
        if (! $journal->file_path || ! Storage::disk('s3')->exists($journal->file_path)) {
            return response()->json(['message' => 'No file available for this journal.'], 404);
        }

        $ext = strtolower($journal->file_type ?? pathinfo($journal->file_path, PATHINFO_EXTENSION));

        $mime = match ($ext) {
            'pdf'  => 'application/pdf',
            'epub' => 'application/epub+zip',
            'doc'  => 'application/msword',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            default => 'application/octet-stream',
        };

        return response()->json([
            'mime' => $mime,
            'name' => $journal->title . '.' . $ext,
            'data' => base64_encode(Storage::disk('s3')->get($journal->file_path)),
        ]);
    }

    public function download(Journal $journal): StreamedResponse|JsonResponse
    {
        if (! $journal->file_path || ! Storage::disk('s3')->exists($journal->file_path)) {
            return response()->json(['message' => 'No file available for this journal.'], 404);
        }

        JournalDownload::create([
            'journal_id' => $journal->id,
            'user_id'    => auth()->id(),
        ]);

        $ext = $journal->file_type
            ?? pathinfo($journal->file_path, PATHINFO_EXTENSION);

        return Storage::disk('s3')->download(
            $journal->file_path,
            $journal->title . '.' . $ext
        );
    }
}
