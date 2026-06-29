<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Acquisition;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AcquisitionController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Acquisition::with('user:id,name');

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

        $acquisition->update($data);

        return response()->json($acquisition->fresh()->load('user:id,name'));
    }

    public function destroy(Acquisition $acquisition): JsonResponse
    {
        $acquisition->delete();

        return response()->json(['message' => 'Acquisition request deleted.']);
    }
}
