<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\FineController;
use App\Http\Controllers\Api\InstitutionalRepositoryController;
use App\Http\Controllers\Api\LoanController;
use App\Http\Controllers\Api\ReportController;
use App\Http\Controllers\Api\ReservationController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes — guests can browse and authenticate
|--------------------------------------------------------------------------
*/
Route::get('/books',        [BookController::class, 'index']);
Route::get('/books/{book}', [BookController::class, 'show']);

Route::get('/repository',                                [InstitutionalRepositoryController::class, 'index']);
Route::get('/repository/{institutionalRepository}',      [InstitutionalRepositoryController::class, 'show']);
Route::get('/repository/{institutionalRepository}/read', [InstitutionalRepositoryController::class, 'read']);

Route::post('/auth/register', [AuthController::class, 'register']);
Route::post('/auth/login',    [AuthController::class, 'login']);

/*
|--------------------------------------------------------------------------
| Authenticated routes — any logged-in, active user
|--------------------------------------------------------------------------
*/
Route::middleware(['auth:sanctum', 'active'])->group(function () {
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me',      [AuthController::class, 'me']);
    Route::patch('/me/profile', [UserController::class, 'updateProfile']);

    // My personal records
    Route::get('/me/loans',        [LoanController::class, 'myLoans']);
    Route::get('/me/reservations', [ReservationController::class, 'myReservations']);
    Route::get('/me/fines',        [FineController::class, 'myFines']);

    /*
    |----------------------------------------------------------------------
    | Authenticated user + admin routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:user,staff,admin')->group(function () {
        Route::get('/repository/{institutionalRepository}/download', [InstitutionalRepositoryController::class, 'download']);

        // Reservations — any logged-in user can reserve
        Route::post('/reservations',                  [ReservationController::class, 'store']);
        Route::delete('/reservations/{reservation}',  [ReservationController::class, 'cancel']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin-only routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:staff,admin')->prefix('admin')->group(function () {
        // Book management (metadata only)
        Route::post('/books',          [BookController::class, 'store']);
        Route::patch('/books/{book}',  [BookController::class, 'update']);
        Route::delete('/books/{book}', [BookController::class, 'destroy']);

        // Institutional repository management
        Route::post('/repository',                           [InstitutionalRepositoryController::class, 'store']);
        Route::post('/repository/{institutionalRepository}', [InstitutionalRepositoryController::class, 'update']); // POST for multipart/form-data
        Route::delete('/repository/{institutionalRepository}', [InstitutionalRepositoryController::class, 'destroy']);

        // Circulation (loans)
        Route::get('/loans',                   [LoanController::class, 'index']);
        Route::post('/loans',                  [LoanController::class, 'store']);
        Route::get('/loans/{loan}',            [LoanController::class, 'show']);
        Route::patch('/loans/{loan}/return',   [LoanController::class, 'returnBook']);

        // Reservations management
        Route::get('/reservations', [ReservationController::class, 'index']);

        // Fines management
        Route::get('/fines',                 [FineController::class, 'index']);
        Route::patch('/fines/{fine}/pay',    [FineController::class, 'pay']);
        Route::patch('/fines/{fine}/waive',  [FineController::class, 'waive']);

        // Reports
        Route::get('/reports/overview',  [ReportController::class, 'overview']);
        Route::get('/reports/overdue',   [ReportController::class, 'overdueLoans']);
        Route::get('/reports/popular',   [ReportController::class, 'popularBooks']);

        // User management
        Route::get('/users',                        [UserController::class, 'index']);
        Route::get('/users/{user}',                 [UserController::class, 'show']);
        Route::patch('/users/{user}/role',          [UserController::class, 'updateRole']);
        Route::patch('/users/{user}/active',        [UserController::class, 'toggleActive']);
        Route::patch('/users/{user}/membership',    [UserController::class, 'updateMembership']);
        Route::delete('/users/{user}',              [UserController::class, 'destroy']);
    });
});
