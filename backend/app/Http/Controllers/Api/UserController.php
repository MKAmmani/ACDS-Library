<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query();

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%");
            });
        }

        if ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        return response()->json(
            $query->orderBy('name')->paginate(20)
        );
    }

    public function show(User $user): JsonResponse
    {
        return response()->json($user);
    }

    public function updateRole(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot change your own role.'], 422);
        }

        $data = $request->validate([
            'role' => ['required', Rule::in(['admin', 'user'])],
        ]);

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        if ($user->is_active) {
            // Deactivating — revoke all tokens so they can't keep using the API
            $user->tokens()->delete();
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->id === $request->user()->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
