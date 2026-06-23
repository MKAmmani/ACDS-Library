<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class FineController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Fine::with([
            'user:id,name,email,member_number',
            'loan.book:id,title,authors',
        ]);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(
            $query->orderByDesc('created_at')->paginate(20)
        );
    }

    public function pay(Fine $fine): JsonResponse
    {
        if ($fine->status !== 'unpaid') {
            return response()->json(['message' => 'Fine is already ' . $fine->status . '.'], 422);
        }

        $fine->update(['status' => 'paid', 'paid_at' => now()]);

        return response()->json($fine->fresh());
    }

    public function waive(Fine $fine): JsonResponse
    {
        if ($fine->status !== 'unpaid') {
            return response()->json(['message' => 'Fine is already ' . $fine->status . '.'], 422);
        }

        $fine->update(['status' => 'waived']);

        return response()->json($fine->fresh());
    }

    public function myFines(Request $request): JsonResponse
    {
        return response()->json(
            Fine::with('loan.book:id,title,authors')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('created_at')
                ->paginate(20)
        );
    }
}
