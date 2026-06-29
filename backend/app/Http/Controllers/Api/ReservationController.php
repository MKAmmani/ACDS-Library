<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Loan;
use App\Models\Reservation;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReservationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Reservation::with(['book:id,title,authors', 'user:id,name,email,member_number']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('book_id')) {
            $query->where('book_id', $request->book_id);
        }

        if ($request->filled('user_id')) {
            $query->where('user_id', $request->user_id);
        }

        return response()->json(
            $query->orderBy('reserved_at')->paginate(20)
        );
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'book_id' => ['required', 'exists:books,id'],
        ]);

        $user = $request->user();
        $book = \App\Models\Book::findOrFail($data['book_id']);

        $alreadyReserved = Reservation::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->whereIn('status', ['pending', 'fulfilled'])
            ->exists();

        if ($alreadyReserved) {
            return response()->json(['message' => 'You already have an active reservation for this book.'], 422);
        }

        $alreadyBorrowed = Loan::where('user_id', $user->id)
            ->where('book_id', $book->id)
            ->active()
            ->exists();

        if ($alreadyBorrowed) {
            return response()->json(['message' => 'You already have an active loan for this book.'], 422);
        }

        // Books with available copies are reserved as "fulfilled" (ready for desk pickup).
        // Books that are all on loan are reserved as "pending" (waitlisted).
        $isAvailable = $book->available_copies > 0;

        $reservation = Reservation::create([
            'book_id'     => $book->id,
            'user_id'     => $user->id,
            'reserved_at' => now(),
            'status'      => $isAvailable ? 'fulfilled' : 'pending',
            'expires_at'  => $isAvailable ? now()->addDays(2) : null,
        ]);

        return response()->json($reservation->load(['book:id,title,authors', 'user:id,name,email']), 201);
    }

    public function cancel(Request $request, Reservation $reservation): JsonResponse
    {
        $user = $request->user();

        // Users can only cancel their own; staff and admins can cancel any
        if (! $user->isAdmin() && ! $user->isStaff() && $reservation->user_id !== $user->id) {
            return response()->json(['message' => 'Forbidden.'], 403);
        }

        // Regular users can only cancel pending reservations (not yet ready for pickup)
        if (! $user->isAdmin() && ! $user->isStaff() && $reservation->status !== 'pending') {
            return response()->json(['message' => 'Only pending reservations can be cancelled.'], 422);
        }

        // Staff/admin can dismiss fulfilled reservations too (e.g. after issuing the loan)
        if (in_array($reservation->status, ['cancelled', 'expired'])) {
            return response()->json(['message' => 'Reservation is already closed.'], 422);
        }

        $reservation->update(['status' => 'cancelled']);

        return response()->json(['message' => 'Reservation cancelled.']);
    }

    public function fulfill(Reservation $reservation): JsonResponse
    {
        if ($reservation->status !== 'pending') {
            return response()->json(['message' => 'Only pending reservations can be marked ready.'], 422);
        }

        $reservation->update([
            'status'     => 'fulfilled',
            'expires_at' => now()->addDays(3),
        ]);

        return response()->json($reservation->fresh()->load(['book:id,title,authors', 'user:id,name,email,member_number']));
    }

    public function myReservations(Request $request): JsonResponse
    {
        return response()->json(
            Reservation::with('book:id,title,authors,call_number')
                ->where('user_id', $request->user()->id)
                ->orderByDesc('reserved_at')
                ->paginate(20)
        );
    }
}
