<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\LoanPolicy;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class LoanPolicyController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(LoanPolicy::orderBy('membership_type')->get());
    }

    public function update(Request $request, LoanPolicy $loanPolicy): JsonResponse
    {
        $data = $request->validate([
            'max_books'    => ['sometimes', 'integer', 'min:1', 'max:50'],
            'loan_days'    => ['sometimes', 'integer', 'min:1', 'max:365'],
            'fine_per_day' => ['sometimes', 'numeric', 'min:0'],
            'max_fine'     => ['nullable', 'numeric', 'min:0'],
        ]);

        $loanPolicy->update($data);

        return response()->json($loanPolicy->fresh());
    }
}
