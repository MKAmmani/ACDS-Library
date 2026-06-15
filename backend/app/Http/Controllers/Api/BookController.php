<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Book::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('author', 'like', "%{$term}%")
                  ->orWhere('subject', 'like', "%{$term}%")
                  ->orWhere('isbn', 'like', "%{$term}%");
            });
        }

        if ($request->filled('format')) {
            $query->where('format', $request->format);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('language')) {
            $query->where('language', $request->language);
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
            'title'            => ['required', 'string', 'max:255'],
            'author'           => ['required', 'string', 'max:255'],
            'subject'          => ['required', 'string', 'max:255'],
            'isbn'             => ['nullable', 'string', 'max:20', 'unique:books,isbn'],
            'format'           => ['required', 'in:book,journal,thesis,ebook'],
            'status'           => ['required', 'in:available,on_loan,digital_access'],
            'description'      => ['nullable', 'string'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'language'         => ['nullable', 'string', 'max:50'],
            'cover_image'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'file'             => ['nullable', 'file', 'mimes:pdf,epub,mobi', 'max:102400'],
        ]);

        if ($request->hasFile('cover_image')) {
            $data['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        if ($request->hasFile('file')) {
            $data['file_path'] = $request->file('file')->store('books', 'local');
        }

        unset($data['file']);

        return response()->json(Book::create($data), 201);
    }

    public function update(Request $request, Book $book): JsonResponse
    {
        $data = $request->validate([
            'title'            => ['sometimes', 'string', 'max:255'],
            'author'           => ['sometimes', 'string', 'max:255'],
            'subject'          => ['sometimes', 'string', 'max:255'],
            'isbn'             => ['nullable', 'string', 'max:20', 'unique:books,isbn,' . $book->id],
            'format'           => ['sometimes', 'in:book,journal,thesis,ebook'],
            'status'           => ['sometimes', 'in:available,on_loan,digital_access'],
            'description'      => ['nullable', 'string'],
            'publication_year' => ['nullable', 'integer', 'min:1000', 'max:' . date('Y')],
            'language'         => ['nullable', 'string', 'max:50'],
            'cover_image'      => ['nullable', 'image', 'mimes:jpeg,jpg,png,webp', 'max:2048'],
            'file'             => ['nullable', 'file', 'mimes:pdf,epub,mobi', 'max:102400'],
        ]);

        if ($request->hasFile('cover_image')) {
            if ($book->cover_image) {
                Storage::disk('public')->delete($book->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('covers', 'public');
        }

        if ($request->hasFile('file')) {
            if ($book->file_path) {
                Storage::disk('local')->delete($book->file_path);
            }
            $data['file_path'] = $request->file('file')->store('books', 'local');
        }

        unset($data['file']);

        $book->update($data);

        return response()->json($book->fresh());
    }

    public function destroy(Book $book): JsonResponse
    {
        if ($book->cover_image) {
            Storage::disk('public')->delete($book->cover_image);
        }

        if ($book->file_path) {
            Storage::disk('local')->delete($book->file_path);
        }

        $book->delete();

        return response()->json(['message' => 'Book deleted successfully.']);
    }

    public function read(Book $book): StreamedResponse|JsonResponse
    {
        if (! $book->file_path || ! Storage::disk('local')->exists($book->file_path)) {
            return response()->json(['message' => 'No readable file available for this book.'], 404);
        }

        // Serve inline so the browser opens the file (e.g. PDF viewer)
        return Storage::disk('local')->response($book->file_path);
    }

    public function download(Book $book): StreamedResponse|JsonResponse
    {
        if (! $book->file_path || ! Storage::disk('local')->exists($book->file_path)) {
            return response()->json(['message' => 'No downloadable file available for this book.'], 404);
        }

        $extension = pathinfo($book->file_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $book->file_path,
            $book->title . '.' . $extension
        );
    }
}
