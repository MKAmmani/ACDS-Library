<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Fine;
use App\Models\Loan;
use App\Models\LoanPolicy;
use App\Models\User;
use App\Notifications\ReservationReadyNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LoanController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Loan::with(['book:id,title,authors', 'user:id,name,email,member_number']);

        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $request->status);
            }
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        if ($request->filled('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        return response()->json(
            $query->orderByDesc('borrowed_at')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $book = \App\Models\Book::findOrFail($data['book_id']);
        $user = User::findOrFail($data['user_id']);

        if ($book->available_copies <= 0) {
            return response()->json(['message' => 'No copies of this book are currently available.'], 422);
        }

        if (! $user->isMembershipActive()) {
            return response()->json(['message' => 'This member\'s library membership has expired.'], 422);
        }

        $policy = LoanPolicy::forUser($user);

        $activeCount = Loan::where('user_id', $user->id)->active()->count();
        if ($activeCount >= $policy->max_books) {
            return response()->json([
                'message' => "Loan limit reached. This member may borrow at most {$policy->max_books} book(s) at a time.",
            ], 422);
        }

        $alreadyBorrowed = Loan::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->exists();
        if ($alreadyBorrowed) {
            return response()->json(['message' => 'This member already has an active loan for this book.'], 422);
        }

        $loan = DB::transaction(function () use ($book, $user, $policy) {
            $book->decrement('available_copies');

            return Loan::create([
                'book_id'     => $book->id,
                'user_id'     => $user->id,
                'borrowed_at' => now(),
                'due_date'    => now()->addDays($policy->loan_days),
                'status'      => 'active',
            ]);
        });

        return response()->json($loan->load(['book:id,title,authors', 'user:id,name,email,member_number']), 201);
    }

    public function show(Loan $loan): JsonResponse
    {
        return response()->json($loan->load(['book:id,title,authors', 'user:id,name,email,member_number', 'fine']));
    }

    public function returnBook(Loan $loan): JsonResponse
    {
        if ($loan->status === 'returned') {
            return response()->json(['message' => 'This book has already been returned.'], 422);
        }

        $fine = null;

        DB::transaction(function () use ($loan, &$fine) {
            $now = now();
            $loan->update(['status' => 'returned', 'returned_at' => $now]);
            $loan->book->increment('available_copies');

            // Promote the next pending reservation (FIFO)
            $nextReservation = $loan->book->reservations()
                ->pending()
                ->orderBy('reserved_at')
                ->first();

            if ($nextReservation) {
                $nextReservation->update([
                    'status'     => 'fulfilled',
                    'expires_at' => $now->copy()->addDays(3),
                ]);
                $nextReservation->user->notify(new ReservationReadyNotification($nextReservation));
            }

            // Generate fine if overdue
            if ($now->gt($loan->due_date->endOfDay())) {
                $daysOverdue = (int) $loan->due_date->endOfDay()->diffInDays($now);
                $policy = LoanPolicy::forUser($loan->user);
                $amount = round($daysOverdue * $policy->fine_per_day, 2);

                if ($policy->max_fine !== null) {
                    $amount = min($amount, $policy->max_fine);
                }

                if ($amount > 0) {
                    $fine = Fine::create([
                        'loan_id'     => $loan->id,
                        'user_id'     => $loan->user_id,
                        'days_overdue' => $daysOverdue,
                        'amount'      => $amount,
                        'status'      => 'unpaid',
                    ]);
                }
            }
        });

        $response = ['loan' => $loan->fresh()->load(['book:id,title', 'user:id,name,email'])];
        if ($fine) {
            $response['fine'] = $fine;
        }

        return response()->json($response);
    }

    public function myLoans(Request $request): JsonResponse
    {
        $query = Loan::with('book:id,title,authors,call_number')
            ->where('user_id', $request->user()->id);

        if ($request->filled('status')) {
            if ($request->status === 'overdue') {
                $query->overdue();
            } else {
                $query->where('status', $request->status);
            }
        }

        return response()->json(
            $query->orderByDesc('borrowed_at')->paginate(20)
        );
    }
}
