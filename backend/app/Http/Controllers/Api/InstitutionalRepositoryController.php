<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\InstitutionalRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InstitutionalRepositoryController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = InstitutionalRepository::query();

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

    public function show(InstitutionalRepository $institutionalRepository): JsonResponse
    {
        return response()->json($institutionalRepository);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'authors'        => ['required', 'string', 'max:255'],
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
            $data['cover_image'] = $request->file('cover_image')->store('repository-covers', 'public');
        }

        $uploaded = $request->file('file');
        $data['file_path'] = $uploaded->store('repositories', 'local');
        $data['file_size'] = $uploaded->getSize();
        $data['file_type'] = strtolower($uploaded->getClientOriginalExtension());

        unset($data['file']);

        return response()->json(InstitutionalRepository::create($data), 201);
    }

    public function update(Request $request, InstitutionalRepository $institutionalRepository): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'authors'        => ['sometimes', 'string', 'max:255'],
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
            if ($institutionalRepository->cover_image) {
                Storage::disk('public')->delete($institutionalRepository->cover_image);
            }
            $data['cover_image'] = $request->file('cover_image')->store('repository-covers', 'public');
        }

        if ($request->hasFile('file')) {
            if ($institutionalRepository->file_path) {
                Storage::disk('local')->delete($institutionalRepository->file_path);
            }
            $uploaded = $request->file('file');
            $data['file_path'] = $uploaded->store('repositories', 'local');
            $data['file_size'] = $uploaded->getSize();
            $data['file_type'] = strtolower($uploaded->getClientOriginalExtension());
        }

        unset($data['file']);

        $institutionalRepository->update($data);

        return response()->json($institutionalRepository->fresh());
    }

    public function destroy(InstitutionalRepository $institutionalRepository): JsonResponse
    {
        if ($institutionalRepository->cover_image) {
            Storage::disk('public')->delete($institutionalRepository->cover_image);
        }

        if ($institutionalRepository->file_path) {
            Storage::disk('local')->delete($institutionalRepository->file_path);
        }

        $institutionalRepository->delete();

        return response()->json(['message' => 'Repository item deleted successfully.']);
    }

    public function read(InstitutionalRepository $institutionalRepository): StreamedResponse|JsonResponse
    {
        if (! $institutionalRepository->file_path || ! Storage::disk('local')->exists($institutionalRepository->file_path)) {
            return response()->json(['message' => 'No file available for this repository item.'], 404);
        }

        return Storage::disk('local')->response($institutionalRepository->file_path);
    }

    public function download(InstitutionalRepository $institutionalRepository): StreamedResponse|JsonResponse
    {
        if (! $institutionalRepository->file_path || ! Storage::disk('local')->exists($institutionalRepository->file_path)) {
            return response()->json(['message' => 'No file available for this repository item.'], 404);
        }

        $ext = $institutionalRepository->file_type
            ?? pathinfo($institutionalRepository->file_path, PATHINFO_EXTENSION);

        return Storage::disk('local')->download(
            $institutionalRepository->file_path,
            $institutionalRepository->title . '.' . $ext
        );
    }
}
