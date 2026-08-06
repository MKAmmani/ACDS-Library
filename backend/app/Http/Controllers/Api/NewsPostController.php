<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\NewsPost;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NewsPostController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $limit = min((int) $request->input('limit', 20), 100);

        $posts = NewsPost::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->limit($limit)
            ->get();

        return response()->json(['data' => $posts]);
    }

    public function store(Request $request): JsonResponse
    {
        $this->nullifyEmptyDate($request);

        $data = $request->validate([
            'tag'          => ['nullable', 'string', 'max:50'],
            'title'        => ['required', 'string', 'max:255'],
            'body'         => ['nullable', 'string'],
            'icon'         => ['nullable', 'string', 'max:50'],
            'tone'         => ['nullable', 'string', 'max:20'],
            'published_at' => ['nullable', 'date'],
        ]);

        $data['published_at'] = $data['published_at'] ?? now();

        return response()->json(NewsPost::create($data), 201);
    }

    public function update(Request $request, NewsPost $newsPost): JsonResponse
    {
        $this->nullifyEmptyDate($request);

        $data = $request->validate([
            'tag'          => ['nullable', 'string', 'max:50'],
            'title'        => ['sometimes', 'string', 'max:255'],
            'body'         => ['nullable', 'string'],
            'icon'         => ['nullable', 'string', 'max:50'],
            'tone'         => ['nullable', 'string', 'max:20'],
            'published_at' => ['nullable', 'date'],
        ]);

        // A blank date field means "leave it as it is", not "clear it".
        if (array_key_exists('published_at', $data) && is_null($data['published_at'])) {
            unset($data['published_at']);
        }

        $newsPost->update($data);

        return response()->json($newsPost->fresh());
    }

    public function destroy(NewsPost $newsPost): JsonResponse
    {
        $newsPost->delete();

        return response()->json(['message' => 'News post deleted successfully.']);
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
