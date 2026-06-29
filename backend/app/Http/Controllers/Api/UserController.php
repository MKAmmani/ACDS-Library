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
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('member_number', 'like', "%{$term}%");
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
        $actor = $request->user();

        if ($user->id === $actor->id) {
            return response()->json(['message' => 'You cannot change your own role.'], 422);
        }

        // Staff cannot manage other staff or admin accounts
        if ($actor->isStaff() && in_array($user->role, ['admin', 'staff'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Staff can only assign the user role; admin can assign any role
        $allowedRoles = $actor->isAdmin() ? ['admin', 'staff', 'user'] : ['user'];

        $data = $request->validate([
            'role' => ['required', Rule::in($allowedRoles)],
        ]);

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function toggleActive(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($user->id === $actor->id) {
            return response()->json(['message' => 'You cannot deactivate your own account.'], 422);
        }

        // Staff cannot manage other staff or admin accounts
        if ($actor->isStaff() && in_array($user->role, ['admin', 'staff'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        if ($user->is_active) {
            $user->tokens()->delete();
        }

        $user->update(['is_active' => ! $user->is_active]);

        return response()->json($user->fresh());
    }

    public function updateProfile(Request $request): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'phone'   => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $request->user()->update($data);

        return response()->json($request->user()->fresh());
    }

    public function updateMembership(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'member_number'         => ['nullable', 'string', 'max:20', 'unique:users,member_number,' . $user->id],
            'membership_type'       => ['sometimes', Rule::in(['student', 'staff', 'faculty', 'public'])],
            'membership_expires_at' => ['nullable', 'date'],
        ]);

        $user->update($data);

        return response()->json($user->fresh());
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        $actor = $request->user();

        if ($user->id === $actor->id) {
            return response()->json(['message' => 'You cannot delete your own account.'], 422);
        }

        // Staff cannot delete other staff or admin accounts
        if ($actor->isStaff() && in_array($user->role, ['admin', 'staff'])) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        $user->tokens()->delete();
        $user->delete();

        return response()->json(['message' => 'User deleted successfully.']);
    }
}
