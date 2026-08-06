<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Acquisition;
use App\Models\Book;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcquisitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Acquisition::with(['user:id,name', 'book:id,title']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('title', 'like', "%{$term}%")
                  ->orWhere('authors', 'like', "%{$term}%")
                  ->orWhere('requested_by', 'like', "%{$term}%");
            });
        }

        return response()->json($query->orderByDesc('created_at')->paginate(20));
    }

    public function store(Request $request): JsonResponse
    {
        if ($request->user()->isAdmin()) {
            return response()->json(['message' => 'Only staff can submit acquisition requests.'], 403);
        }

        $data = $request->validate([
            'title'          => ['required', 'string', 'max:255'],
            'authors'        => ['nullable', 'string', 'max:255'],
            'requested_by'   => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'copies'         => ['nullable', 'integer', 'min:1'],
            'notes'          => ['nullable', 'string'],
        ]);

        $data['user_id'] = $request->user()->id;
        $data['status']  = 'awaiting';

        return response()->json(Acquisition::create($data)->load('user:id,name'), 201);
    }

    public function update(Request $request, Acquisition $acquisition): JsonResponse
    {
        $data = $request->validate([
            'title'          => ['sometimes', 'string', 'max:255'],
            'authors'        => ['nullable', 'string', 'max:255'],
            'requested_by'   => ['nullable', 'string', 'max:255'],
            'estimated_cost' => ['nullable', 'numeric', 'min:0'],
            'copies'         => ['nullable', 'integer', 'min:1'],
            'status'         => ['sometimes', 'in:awaiting,ordered,received,declined'],
            'notes'          => ['nullable', 'string'],
        ]);

        // Approving or declining a pending request is an administrator-only decision.
        $isApprovalDecision = ($data['status'] ?? null) !== null
            && in_array($data['status'], ['ordered', 'declined'], true)
            && $acquisition->status === 'awaiting';

        if ($isApprovalDecision && ! $request->user()->isAdmin()) {
            return response()->json(['message' => 'Only an administrator can approve or decline acquisition requests.'], 403);
        }

        $acquisition->update($data);

        return response()->json($acquisition->fresh()->load('user:id,name'));
    }

    public function destroy(Acquisition $acquisition): JsonResponse
    {
        $acquisition->delete();

        return response()->json(['message' => 'Acquisition request deleted.']);
    }

    /**
     * Turn a received acquisition into a catalog entry — creates the Book
     * record from the request's details and links it back to this request.
     */
    public function catalog(Request $request, Acquisition $acquisition): JsonResponse
    {
        if ($request->user()->isAdmin()) {
            return response()->json(['message' => 'Only staff can catalog received acquisitions.'], 403);
        }

        if ($acquisition->status !== 'received') {
            return response()->json(['message' => 'Only received acquisitions can be catalogued.'], 422);
        }

        if ($acquisition->book_id) {
            return response()->json(['message' => 'This acquisition has already been catalogued.'], 422);
        }

        $book = Book::create([
            'title'             => $acquisition->title,
            'authors'           => $acquisition->authors,
            'subject_area'      => 'General',
            'language'          => 'English',
            'number_of_copies'  => $acquisition->copies,
            'available_copies'  => $acquisition->copies,
        ]);

        $acquisition->update(['book_id' => $book->id]);

        return response()->json($acquisition->fresh()->load(['user:id,name', 'book']));
    }
}
