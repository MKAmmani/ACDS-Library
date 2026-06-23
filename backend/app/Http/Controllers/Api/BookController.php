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
            $query->where('subject_area', 'like', '%' . $request->subject_area . '%');
        }

        return response()->json(
            $query->orderBy('title')->paginate(20)
        );
    }

    public function show(Book $book): JsonResponse
    {
        return response()->json($book);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'           => ['required', 'string', 'max:255'],
            'authors'         => ['required', 'string', 'max:255'],
            'publisher'       => ['nullable', 'string', 'max:255'],
            'year'            => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'isbn'            => ['nullable', 'string', 'max:20', 'unique:books,isbn'],
            'edition'         => ['nullable', 'string', 'max:50'],
            'description'     => ['nullable', 'string'],
            'subject_area'    => ['required', 'string', 'max:255'],
            'call_number'     => ['nullable', 'string', 'max:50'],
            'shelf_location'  => ['nullable', 'string', 'max:100'],
            'language'        => ['nullable', 'string', 'max:50'],
            'format'          => ['nullable', 'string', 'max:50'],
            'material_type'   => ['nullable', 'string', 'max:50'],
            'number_of_copies' => ['nullable', 'integer', 'min:0'],
            'cover_treatment' => ['nullable', 'string', 'max:100'],
        ]);

        $data['available_copies'] = $data['number_of_copies'] ?? 1;

        return response()->json(Book::create($data), 201);
    }

    public function update(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'title'           => ['sometimes', 'string', 'max:255'],
            'authors'         => ['sometimes', 'string', 'max:255'],
            'publisher'       => ['nullable', 'string', 'max:255'],
            'year'            => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'isbn'            => ['nullable', 'string', 'max:20', 'unique:books,isbn,' . $book->id],
            'edition'         => ['nullable', 'string', 'max:50'],
            'description'     => ['nullable', 'string'],
            'subject_area'    => ['sometimes', 'string', 'max:255'],
            'call_number'     => ['nullable', 'string', 'max:50'],
            'shelf_location'  => ['nullable', 'string', 'max:100'],
            'language'        => ['nullable', 'string', 'max:50'],
            'format'          => ['nullable', 'string', 'max:50'],
            'material_type'   => ['nullable', 'string', 'max:50'],
            'number_of_copies' => ['nullable', 'integer', 'min:0'],
            'cover_treatment' => ['nullable', 'string', 'max:100'],
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
}
