<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BookController;
use App\Http\Controllers\Api\UserController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes — guests can browse books and authenticate
|--------------------------------------------------------------------------
*/
Route::get('/books',             [BookController::class, 'index']);
Route::get('/books/{book}',      [BookController::class, 'show']);
Route::get('/books/{book}/read', [BookController::class, 'read']);

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

    /*
    |----------------------------------------------------------------------
    | System user routes — registered users (role: user or admin)
    |----------------------------------------------------------------------
    */
    Route::middleware('role:user,admin')->group(function () {
        Route::get('/books/{book}/download', [BookController::class, 'download']);
    });

    /*
    |----------------------------------------------------------------------
    | Admin-only routes
    |----------------------------------------------------------------------
    */
    Route::middleware('role:admin')->prefix('admin')->group(function () {
        // Book management
        Route::post('/books',         [BookController::class, 'store']);
        Route::post('/books/{book}',  [BookController::class, 'update']); // POST for multipart/form-data with files
        Route::delete('/books/{book}',[BookController::class, 'destroy']);

        // User management
        Route::get('/users',                       [UserController::class, 'index']);
        Route::get('/users/{user}',                [UserController::class, 'show']);
        Route::patch('/users/{user}/role',         [UserController::class, 'updateRole']);
        Route::patch('/users/{user}/active',       [UserController::class, 'toggleActive']);
        Route::delete('/users/{user}',             [UserController::class, 'destroy']);
    });
});
