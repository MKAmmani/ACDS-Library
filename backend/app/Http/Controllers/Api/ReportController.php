<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Book;
use App\Models\Fine;
use App\Models\InboxThread;
use App\Models\Loan;
use App\Models\Reservation;
use App\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function overview(): JsonResponse
    {
        $totalReturned = Loan::where('status', 'returned')->count();
        $onTime = Loan::where('status', 'returned')
            ->whereColumn('returned_at', '<=', DB::raw('due_date'))
            ->count();
        $onTimeRate = $totalReturned > 0 ? round(($onTime / $totalReturned) * 100) : 0;

        $totalCopies     = (int) Book::sum('number_of_copies');
        $availableCopies = (int) Book::sum('available_copies');
        $utilization     = $totalCopies > 0 ? round((($totalCopies - $availableCopies) / $totalCopies) * 100) : 0;

        $memberCount = User::where('role', 'user')->count();
        $activeLoans = Loan::active()->count();
        $avgLoans    = $memberCount > 0 ? round($activeLoans / $memberCount, 1) : 0;

        return response()->json([
            'books' => [
                'total'            => Book::count(),
                'total_copies'     => $totalCopies,
                'available_copies' => $availableCopies,
                'utilization_pct'  => $utilization,
            ],
            'loans' => [
                'active'          => $activeLoans,
                'overdue'         => Loan::overdue()->count(),
                'returned'        => $totalReturned,
                'this_month'      => Loan::whereYear('borrowed_at', now()->year)->whereMonth('borrowed_at', now()->month)->count(),
                'on_time_rate_pct' => $onTimeRate,
                'avg_per_member'  => $avgLoans,
            ],
            'reservations' => [
                'pending'   => Reservation::pending()->count(),
                'fulfilled' => Reservation::where('status', 'fulfilled')->count(),
            ],
            'fines' => [
                'unpaid_count'          => Fine::unpaid()->count(),
                'unpaid_amount'         => (float) Fine::unpaid()->sum('amount'),
                'collected_this_month'  => (float) Fine::where('status', 'paid')
                    ->whereYear('paid_at', now()->year)
                    ->whereMonth('paid_at', now()->month)
                    ->sum('amount'),
            ],
            'users' => [
                'total'                   => User::count(),
                'members'                 => $memberCount,
                'active_borrowers_month'  => (int) Loan::select('user_id')
                    ->whereYear('borrowed_at', now()->year)
                    ->whereMonth('borrowed_at', now()->month)
                    ->distinct()
                    ->count('user_id'),
                'new_this_month'          => User::whereYear('created_at', now()->year)->whereMonth('created_at', now()->month)->count(),
            ],
            'inbox' => [
                'unread' => InboxThread::where('channel','user_to_staff')->where('last_sender_role','user')->where('status','open')->count(),
            ],
        ]);
    }

    public function overdueLoans(): JsonResponse
    {
        return response()->json(
            Loan::overdue()
                ->with(['book:id,title,authors,call_number', 'user:id,name,email,member_number,phone,membership_type'])
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

    public function dailyActivity(Request $request): JsonResponse
    {
        $days = min((int) $request->get('days', 12), 60);
        $from = now()->subDays($days - 1)->startOfDay();

        $issued = DB::table('loans')
            ->selectRaw('DATE(borrowed_at) as date, COUNT(*) as count')
            ->where('borrowed_at', '>=', $from)
            ->groupByRaw('DATE(borrowed_at)')
            ->pluck('count', 'date');

        $returned = DB::table('loans')
            ->selectRaw('DATE(returned_at) as date, COUNT(*) as count')
            ->where('status', 'returned')
            ->where('returned_at', '>=', $from)
            ->groupByRaw('DATE(returned_at)')
            ->pluck('count', 'date');

        $result = [];
        for ($i = $days - 1; $i >= 0; $i--) {
            $date     = now()->subDays($i)->format('Y-m-d');
            $result[] = [
                'date'     => $date,
                'label'    => now()->subDays($i)->format('d'),
                'issued'   => (int) ($issued[$date] ?? 0),
                'returned' => (int) ($returned[$date] ?? 0),
            ];
        }

        return response()->json($result);
    }

    public function subjectStats(): JsonResponse
    {
        $data = DB::table('loans')
            ->join('books', 'loans.book_id', '=', 'books.id')
            ->selectRaw('books.subject_area, COUNT(*) as loan_count')
            ->groupBy('books.subject_area')
            ->orderByDesc('loan_count')
            ->limit(8)
            ->get();

        return response()->json($data);
    }
}

