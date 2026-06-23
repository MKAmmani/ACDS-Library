<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class ReportController extends Controller
{
    public function overview(): JsonResponse
    {
        return response()->json([
            'books' => [
                'total'             => Book::count(),
                'total_copies'      => Book::sum('number_of_copies'),
                'available_copies'  => Book::sum('available_copies'),
            ],
            'loans' => [
                'active'   => Loan::active()->count(),
                'overdue'  => Loan::overdue()->count(),
                'returned' => Loan::where('status', 'returned')->count(),
            ],
            'reservations' => [
                'pending'   => Reservation::pending()->count(),
            ],
            'fines' => [
                'unpaid_count'  => Fine::unpaid()->count(),
                'unpaid_amount' => Fine::unpaid()->sum('amount'),
            ],
            'users' => [
                'total'  => User::count(),
                'active' => User::where('is_active', true)->count(),
            ],
        ]);
    }

    public function overdueLoans(): JsonResponse
    {
        return response()->json(
            Loan::overdue()
                ->with(['book:id,title,authors,call_number', 'user:id,name,email,member_number,phone'])
                ->orderBy('due_date')
                ->paginate(20)
        );
    }

    public function popularBooks(): JsonResponse
    {
        $books = Book::withCount(['loans as total_loans'])
            ->orderByDesc('total_loans')
            ->limit(20)
            ->get(['id', 'title', 'authors', 'subject_area', 'available_copies', 'number_of_copies']);

        return response()->json($books);
    }
}
