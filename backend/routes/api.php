<?php

use App\Http\Controllers\Api\AcquisitionController;
use App\Http\Controllers\Api\InboxController;
use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\FineController;
use App\Http\Controllers\Api\InstitutionalRepositoryController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\LoanPolicyController;
use App\Http\Controllers\Api\MarcImportController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes â€” guests can browse and authenticate
|--------------------------------------------------------------------------
*/
Route::get('/books',        [BookController::class, 'index']);
Route::get('/books/{book}', [BookController::class, 'show']);

Route::get('/repository',                                [InstitutionalRepositoryController::class, 'index']);
Route::get('/repository/stats',                          [InstitutionalRepositoryController::class, 'stats']);
Route::get('/repository/{institutionalRepository}',      [InstitutionalRepositoryController::class, 'show']);
Route::get('/repository/{institutionalRepository}/read', [InstitutionalRepositoryController::class, 'read']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Authenticated routes â€” any logged-in, active user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::patch('/me/profile', [UserController::class, 'updateProfile']);

    Route::get('/me/loans',        [LoanController::class, 'myLoans']);
    Route::get('/me/reservations', [ReservationController::class, 'myReservations']);
    Route::get('/me/fines',        [FineController::class, 'myFines']);

    Route::middleware('role:user,staff,admin')->group(function () {
        Route::get('/repository/{institutionalRepository}/download', [InstitutionalRepositoryController::class, 'download']);

        Route::post('/reservations',                 [ReservationController::class, 'store']);
        Route::delete('/reservations/{reservation}', [ReservationController::class, 'cancel']);

        // Members — Ask-Librarian thread system
        Route::post('/inbox/submit',                                   [InboxController::class, 'submit']);
        Route::get('/inbox/threads',                                   [InboxController::class, 'myThreads']);
        Route::get('/inbox/threads/{inboxThread}/messages',            [InboxController::class, 'myMessages']);
        Route::post('/inbox/threads/{inboxThread}/reply',              [InboxController::class, 'userReply']);
    });

    /*
    |----------------------------------------------------------------------
    | Staff + Admin routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:staff,admin')->prefix('admin')->group(function () {
        // Books
        Route::post('/books/import',   [MarcImportController::class, 'store']);
        Route::post('/books',          [BookController::class, 'store']);
        Route::patch('/books/{book}',  [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);
        Route::get('/books/{book}/copies',  [BookController::class, 'copies']);
        Route::put('/books/{book}/copies',  [BookController::class, 'saveCopies']);

        // Repository
        Route::post('/repository',                                [InstitutionalRepositoryController::class, 'store']);
        Route::post('/repository/{institutionalRepository}',      [InstitutionalRepositoryController::class, 'update']);
        Route::delete('/repository/{institutionalRepository}',    [InstitutionalRepositoryController::class, 'destroy']);

        // Circulation (loans)
        Route::get('/loans',                   [LoanController::class, 'index']);
        Route::post('/loans',                  [LoanController::class, 'store']);
        Route::get('/loans/{loan}',            [LoanController::class, 'show']);
        Route::patch('/loans/{loan}/return',   [LoanController::class, 'returnBook']);
        Route::patch('/loans/{loan}/renew',    [LoanController::class, 'renew']);

        // Reservations
        Route::get('/reservations',                              [ReservationController::class, 'index']);
        Route::patch('/reservations/{reservation}/fulfill',      [ReservationController::class, 'fulfill']);

        // Fines
        Route::get('/fines',                 [FineController::class, 'index']);
        Route::patch('/fines/{fine}/pay',    [FineController::class, 'pay']);
        Route::patch('/fines/{fine}/waive',  [FineController::class, 'waive']);

        // Reports
        Route::get('/reports/overview',  [ReportController::class, 'overview']);
        Route::get('/reports/overdue',   [ReportController::class, 'overdueLoans']);
        Route::get('/reports/popular',   [ReportController::class, 'popularBooks']);
        Route::get('/reports/subjects',  [ReportController::class, 'subjectStats']);
        Route::get('/reports/activity',  [ReportController::class, 'dailyActivity']);

        // Inbox — thread-based two-channel messaging
        Route::get('/inbox',                                           [InboxController::class, 'index']);
        Route::post('/inbox/compose',                                  [InboxController::class, 'compose']);
        Route::post('/inbox/log-query',                                [InboxController::class, 'logQuery']);
        Route::get('/inbox/{inboxThread}/messages',                    [InboxController::class, 'messages']);
        Route::post('/inbox/{inboxThread}/reply',                      [InboxController::class, 'reply']);
        Route::patch('/inbox/{inboxThread}/close',                     [InboxController::class, 'close']);
        Route::patch('/inbox/{inboxThread}/open',                      [InboxController::class, 'open']);
        Route::delete('/inbox/{inboxThread}',                          [InboxController::class, 'destroy']);

        // Settings (loan policies)
        Route::get('/settings/policies',                [LoanPolicyController::class, 'index']);
        Route::patch('/settings/policies/{loanPolicy}', [LoanPolicyController::class, 'update']);

        // Acquisitions
        Route::get('/acquisitions',                [AcquisitionController::class, 'index']);
        Route::post('/acquisitions',               [AcquisitionController::class, 'store']);
        Route::patch('/acquisitions/{acquisition}', [AcquisitionController::class, 'update']);
        Route::delete('/acquisitions/{acquisition}', [AcquisitionController::class, 'destroy']);

        // Users
        Route::get('/users',                        [UserController::class, 'index']);
        Route::get('/users/{user}',                 [UserController::class, 'show']);
        Route::patch('/users/{user}/role',          [UserController::class, 'updateRole']);
        Route::patch('/users/{user}/active',        [UserController::class, 'toggleActive']);
        Route::patch('/users/{user}/membership',    [UserController::class, 'updateMembership']);
        Route::delete('/users/{user}',              [UserController::class, 'destroy']);
    });
});


