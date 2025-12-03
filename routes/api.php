<?php

use App\Http\Controllers\Api\AuthController;
use App\Http\Controllers\Api\BoardController;
use App\Http\Controllers\Api\ColumnController;
use App\Http\Controllers\Api\CardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "api" middleware group. Make something great!
|
*/

// Auth routes (public)
Route::get('/auth/google', [AuthController::class, 'redirectToGoogle']);
Route::post('/auth/google/callback', [AuthController::class, 'googleCallback']);
Route::get('/auth/user', [AuthController::class, 'getUser']);

// Protected routes (require authentication)
Route::middleware('auth:sanctum')->group(function () {
    // Auth routes
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    // Board routes
    Route::apiResource('boards', BoardController::class);

    // Column routes
    Route::post('boards/{board}/columns', [ColumnController::class, 'store']);
    Route::put('columns/{column}', [ColumnController::class, 'update']);
    Route::delete('columns/{column}', [ColumnController::class, 'destroy']);
    Route::post('columns/reorder', [ColumnController::class, 'reorder']);

    // Card routes
    Route::post('columns/{column}/cards', [CardController::class, 'store']);
    Route::get('cards/{card}', [CardController::class, 'show']);
    Route::put('cards/{card}', [CardController::class, 'update']);
    Route::delete('cards/{card}', [CardController::class, 'destroy']);
    Route::post('cards/{card}/move', [CardController::class, 'move']);
    Route::put('cards/{card}/toggle-complete', [CardController::class, 'toggleComplete']);
});

// Health check
Route::get('/health-check', function () {
    return response()->json([
        'success' => true,
        'message' => 'API is running',
        'timestamp' => now()
    ]);
});
