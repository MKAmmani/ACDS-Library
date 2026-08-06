<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Book::query();

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

        if ($request->filled('material_type')) {
            $query->where('material_type', $request->material_type);
        }

        if ($request->filled('language')) {
            $query->where('language', $request->language);
        }

        if ($request->filled('subject_area')) {
            $query->whereIn('subject_area', explode(',', $request->subject_area));
        }

        if ($request->filled('shelf_location')) {
            $query->where('shelf_location', $request->shelf_location);
        }

        if ($request->filled('availability')) {
            if ($request->availability === 'available') {
                $query->where('available_copies', '>', 0);
            } elseif ($request->availability === 'on_loan') {
                $query->where('available_copies', '<=', 0);
            }
        }

        if ($request->filled('year_from')) {
            $query->where('year', '>=', (int) $request->year_from);
        }

        if ($request->filled('year_to')) {
            $query->where('year', '<=', (int) $request->year_to);
        }

        // Only applied when explicitly requested — the Staff/Admin Catalog Manager
        // never sends this, so it keeps seeing every book regardless of archive status.
        if ($request->has('archived')) {
            $query->where('is_archived', $request->boolean('archived'));
        }

        if ($request->filled('archive_reason')) {
            $query->where('archive_reason', $request->archive_reason);
        }

        if ($request->input('sort') === 'newest') {
            $query->orderByDesc('created_at')->orderByDesc('id');
        } else {
            $query->orderBy('title');
        }

        $perPage = $request->filled('per_page') ? min((int) $request->per_page, 50) : 20;

        return response()->json($query->paginate($perPage));
    }

    /**
     * Distinct facet values for the catalog filters — subjects (for the Subject
     * filter) and shelf locations (for Browse Collections), ordered by how many
     * books carry each, so the most-used appear first.
     */
    public function facets(): JsonResponse
    {
        $subjects = Book::query()
            ->whereNotNull('subject_area')->where('subject_area', '!=', '')
            ->selectRaw('subject_area as name, COUNT(*) as count')
            ->groupBy('subject_area')->orderByDesc('count')->orderBy('subject_area')
            ->limit(30)->get();

        $shelves = Book::query()
            ->whereNotNull('shelf_location')->where('shelf_location', '!=', '')
            ->selectRaw('shelf_location as name, COUNT(*) as count')
            ->groupBy('shelf_location')->orderByDesc('count')->orderBy('shelf_location')
            ->limit(20)->get();

        return response()->json([
            'subjects'        => $subjects,
            'shelf_locations' => $shelves,
        ]);
    }

    public function show(Book $book): JsonResponse
    {
        return response()->json($book);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'authors'         => ['nullable', 'string', 'max:255'],
            'publisher'       => ['nullable', 'string', 'max:255'],
            'year'            => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'isbn'            => ['nullable', 'string', 'max:20', 'unique:books,isbn'],
            'edition'         => ['nullable', 'string', 'max:50'],
            'description'     => ['nullable', 'string'],
            'subject_area'    => ['nullable', 'string', 'max:255'],
            'call_number'     => ['nullable', 'string', 'max:50'],
            'shelf_location'  => ['nullable', 'string', 'max:100'],
            'language'        => ['nullable', 'string', 'max:50'],
            'format'          => ['nullable', 'string', 'max:50'],
            'material_type'   => ['nullable', 'string', 'max:50'],
            'number_of_copies' => ['nullable', 'integer', 'min:0'],
            'cover_treatment' => ['nullable', 'string', 'max:100'],
            'is_archived'     => ['nullable', 'boolean'],
            'archive_reason'  => ['nullable', 'string', 'max:255'],
        ]);

        $data['available_copies'] = $data['number_of_copies'] ?? 1;
        $data['subject_area']     = $data['subject_area'] ?? 'General';
        $data['language']         = $data['language'] ?? 'English';

        return response()->json(Book::create($data), 201);
    }

    public function update(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'title'           => ['sometimes', 'string', 'max:255'],
            'authors'         => ['nullable', 'string', 'max:255'],
            'publisher'       => ['nullable', 'string', 'max:255'],
            'year'            => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'isbn'            => ['nullable', 'string', 'max:20', 'unique:books,isbn,' . $book->id],
            'edition'         => ['nullable', 'string', 'max:50'],
            'description'     => ['nullable', 'string'],
            'subject_area'    => ['nullable', 'string', 'max:255'],
            'call_number'     => ['nullable', 'string', 'max:50'],
            'shelf_location'  => ['nullable', 'string', 'max:100'],
            'language'        => ['nullable', 'string', 'max:50'],
            'format'          => ['nullable', 'string', 'max:50'],
            'material_type'   => ['nullable', 'string', 'max:50'],
            'number_of_copies' => ['nullable', 'integer', 'min:0'],
            'cover_treatment' => ['nullable', 'string', 'max:100'],
            'is_archived'     => ['nullable', 'boolean'],
            'archive_reason'  => ['nullable', 'string', 'max:255'],
        ]);

        // Keep available_copies in sync when total copies change
        if (isset($data['number_of_copies'])) {
            $delta = $data['number_of_copies'] - $book->number_of_copies;
            $data['available_copies'] = max(0, $book->available_copies + $delta);
        }

        $book->update($data);

        return response()->json($book->fresh());
    }

    public function destroy(Book $book): JsonResponse
    {
        $book->delete();

        return response()->json(['message' => 'Book deleted successfully.']);
    }

    /**
     * List accession numbers for each physical copy of a book. Returns one slot
     * per copy (1 … number_of_copies), pre-filled with any stored accession number.
     */
    public function copies(Book $book): JsonResponse
    {
        $total = max(1, (int) $book->number_of_copies);
        $stored = $book->copies()->pluck('accession_number', 'copy_number');

        $copies = [];
        for ($n = 1; $n <= $total; $n++) {
            $copies[] = [
                'copy_number'      => $n,
                'accession_number' => $stored[$n] ?? null,
            ];
        }

        return response()->json([
            'book_id'          => $book->id,
            'title'            => $book->title,
            'number_of_copies' => $total,
            'copies'           => $copies,
        ]);
    }

    /**
     * Upsert the accession number for each copy of a book.
     */
    public function saveCopies(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'copies'                     => ['required', 'array'],
            'copies.*.copy_number'       => ['required', 'integer', 'min:1'],
            'copies.*.accession_number'  => ['nullable', 'string', 'max:100'],
        ]);

        foreach ($data['copies'] as $copy) {
            $accession = isset($copy['accession_number']) ? trim((string) $copy['accession_number']) : '';

            $book->copies()->updateOrCreate(
                ['copy_number' => $copy['copy_number']],
                ['accession_number' => $accession !== '' ? $accession : null],
            );
        }

        return response()->json([
            'message' => 'Accession numbers saved.',
            'copies'  => $book->copies()->get(['copy_number', 'accession_number']),
        ]);
    }
}
