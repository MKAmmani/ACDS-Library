<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanPolicy;
use App\Models\User;
use App\Notifications\AccountCreatedNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $actor = $request->user();
        $query = User::query();

        // Staff may only ever see/manage plain member accounts.
        if ($actor->isStaff()) {
            $query->where('role', 'user');
        } elseif ($request->filled('role')) {
            $query->where('role', $request->role);
        }

        if ($request->filled('search')) {
            $term = $request->search;
            $query->where(function ($q) use ($term) {
                $q->where('name', 'like', "%{$term}%")
                  ->orWhere('email', 'like', "%{$term}%")
                  ->orWhere('member_number', 'like', "%{$term}%");
            });
        }

        if ($request->filled('status')) {
            match ($request->status) {
                'suspended' => $query->where('is_active', false),
                'standing'  => $query->where('is_active', true)->whereDoesntHave('fines', fn ($q) => $q->unpaid()),
                'fines'     => $query->whereHas('fines', fn ($q) => $q->unpaid()),
                default     => null,
            };
        }

        if ($request->filled('is_active')) {
            $query->where('is_active', filter_var($request->is_active, FILTER_VALIDATE_BOOLEAN));
        }

        $query->withCount(['loans as loans_active_count' => fn ($q) => $q->where('status', 'active')])
              ->withSum(['fines as fines_unpaid_total' => fn ($q) => $q->unpaid()], 'amount');

        $paginated = $query->orderBy('name')->paginate(20);

        $policies = LoanPolicy::all()->keyBy('membership_type');
        $paginated->getCollection()->transform(function (User $user) use ($policies) {
            $user->loans_limit        = $policies->get($user->membership_type)?->max_books;
            $user->fines_unpaid_total = $user->fines_unpaid_total ?? 0;
            return $user;
        });

        return response()->json($paginated);
    }

    public function stats(Request $request): JsonResponse
    {
        $actor = $request->user();

        // Scoped to a single role — used by the Members (staff) page.
        if ($actor->isStaff() || $request->filled('role')) {
            $role = $actor->isStaff() ? 'user' : $request->role;
            $base = User::where('role', $role);

            return response()->json([
                'total'         => (clone $base)->count(),
                'new_this_week' => (clone $base)->where('created_at', '>=', now()->subWeek())->count(),
                'suspended'     => (clone $base)->where('is_active', false)->count(),
                'with_fines'    => (clone $base)->whereHas('fines', fn ($q) => $q->unpaid())->count(),
            ]);
        }

        // Full breakdown across all roles — used by the admin Staff Accounts page.
        return response()->json([
            'total'         => User::count(),
            'admins'        => User::where('role', 'admin')->count(),
            'staff'         => User::where('role', 'staff')->count(),
            'users'         => User::where('role', 'user')->count(),
            'suspended'     => User::where('is_active', false)->count(),
            'new_this_week' => User::where('created_at', '>=', now()->subWeek())->count(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $actor        = $request->user();
        $allowedRoles = $actor->isAdmin() ? ['admin', 'staff', 'user'] : ['user'];

        $data = $request->validate([
            'name'            => ['required', 'string', 'max:255'],
            'email'           => ['required', 'email', 'unique:users,email'],
            'role'            => ['required', Rule::in($allowedRoles)],
            'phone'           => ['nullable', 'string', 'max:20'],
            'address'         => ['nullable', 'string'],
            'membership_type' => ['nullable', Rule::in(['buk_staff', 'pg_student', 'independent_researcher', 'international_researcher'])],
        ]);

        $password = Str::password(12);

        $payload = [
            'name'      => $data['name'],
            'email'     => $data['email'],
            'password'  => $password,
            'role'      => $data['role'],
            'is_active' => true,
            'phone'     => $data['phone'] ?? null,
            'address'   => $data['address'] ?? null,
        ];

        if ($data['role'] === 'user') {
            $payload['membership_type'] = $data['membership_type'] ?? 'pg_student';
            $payload['member_number']   = $this->generateMemberNumber();
        }

        $user = User::create($payload);

        try {
            $user->notify(new AccountCreatedNotification($user, $password));
        } catch (\Throwable $e) {
            // Mail transport may be unavailable in local/dev — credentials are
            // still returned in the response below so the account is usable.
        }

        return response()->json([
            'user'               => $user->fresh(),
            'generated_password' => $password,
        ], 201);
    }

    public function update(Request $request, User $user): JsonResponse
    {
        $data = $request->validate([
            'name'    => ['sometimes', 'string', 'max:255'],
            'email'   => ['sometimes', 'email', Rule::unique('users', 'email')->ignore($user->id)],
            'phone'   => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ]);

        $user->update($data);

        return response()->json($user->fresh());
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
            'membership_type'       => ['sometimes', Rule::in(['buk_staff', 'pg_student', 'independent_researcher', 'international_researcher'])],
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

    private function generateMemberNumber(): string
    {
        $year  = now()->year;
        $count = User::where('member_number', 'like', "LIB-{$year}-%")->count();

        return sprintf('LIB-%d-%04d', $year, $count + 1);
    }
}
