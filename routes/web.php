<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\AuthController;

Route::get('/', function () {
    return view('welcome');
});

/*
|--------------------------------------------------------------------------
| Google OAuth Routes (Backend-Driven Authorization Code Flow)
|--------------------------------------------------------------------------
|
| These routes handle the OAuth 2.0 authorization code flow:
|
| 1. GET /api/auth/google
|    - Redirects user to Google's OAuth consent screen
|    - No authentication required
|
| 2. GET /api/auth/google/callback
|    - Receives authorization code from Google
|    - Exchanges code for access token
|    - Verifies user identity
|    - Creates/updates user in database
|    - Generates Sanctum token and stores in HTTP-only cookie
|    - Redirects to frontend /auth/success or /auth/error?reason=...
|
| After successful authentication, frontend can retrieve user data
| by calling GET /api/auth/me (defined in api.php) with the cookie.
|
*/
Route::get('/api/auth/google', [AuthController::class, 'redirectToGoogle'])
    ->name('auth.google');

Route::get('/api/auth/google/callback', [AuthController::class, 'handleGoogleCallback'])
    ->name('auth.google.callback');
