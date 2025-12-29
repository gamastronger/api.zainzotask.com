<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Google\Client as GoogleClient;

class AuthController extends Controller
{
    /**
     * Redirect to Google OAuth
     */
    public function redirectToGoogle(Request $request)
    {
    //     dd([
    //     'app_env' => app()->environment(),
    //     'app_url' => config('app.url'),
    //     'frontend_url' => config('app.frontend_url'),
    //     'google_redirect' => config('services.google.redirect'),
    //     'raw_env_app_url' => env('APP_URL'),
    // ]);

        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        $url = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

        // Check if request expects JSON (AJAX call)
        if ($request->expectsJson() || $request->wantsJson()) {
            return response()->json([
                'success' => true,
                'data' => [
                    'url' => $url
                ]
            ]);
        }

        // Otherwise, redirect directly
        return redirect($url);
    }

    /**
     * Handle Google OAuth callback with authorization code
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            $code = $request->input('code');

            if (!$code) {
                return redirect(config('app.frontend_url') . '/auth/error?message=No authorization code');
            }

            // Exchange code for token
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect'));

            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                return redirect(config('app.frontend_url') . '/auth/error?message=' . urlencode($token['error']));
            }

            // Verify and get user info from ID token
            $idToken = $token['id_token'] ?? null;
            if (!$idToken) {
                return redirect(config('app.frontend_url') . '/auth/error?message=No ID token received');
            }

            $payload = $client->verifyIdToken($idToken);
            if (!$payload) {
                return redirect(config('app.frontend_url') . '/auth/error?message=Invalid token');
            }

            // Get user info from token
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $picture = $payload['picture'] ?? null;

            // Find or create user
            $user = User::updateOrCreate(
                ['google_id' => $googleId],
                [
                    'name' => $name,
                    'email' => $email,
                    'picture' => $picture,
                    'provider' => 'google',
                    'email_verified_at' => now(),
                ]
            );

            // Create Sanctum token
            $authToken = $user->createToken('auth_token')->plainTextToken;

            // Create default board if user is new
            if ($user->boards()->count() === 0) {
                $board = $user->boards()->create([
                    'title' => 'My Board',
                    'description' => 'Default board'
                ]);

                // Create default columns
                $columns = [
                    ['title' => 'Todo', 'color' => '#E8EAF6', 'position' => 0],
                    ['title' => 'In Progress', 'color' => '#E3F2FD', 'position' => 1],
                    ['title' => 'Done', 'color' => '#E0F2F1', 'position' => 2],
                ];

                foreach ($columns as $column) {
                    $board->columns()->create($column);
                }
            }

            // Redirect to frontend with token and user data
            $frontendUrl = config('app.frontend_url') . '/auth/callback';
            $params = [
                'token' => $authToken,
                'user' => json_encode([
                    'id' => $user->id,
                    'name' => $user->name,
                    'email' => $user->email,
                    'picture' => $user->picture,
                ])
            ];

            return redirect($frontendUrl . '?' . http_build_query($params));

        } catch (\Exception $e) {
            return redirect(config('app.frontend_url') . '/auth/error?message=' . urlencode($e->getMessage()));
        }
    }

    /**
     * Handle Google OAuth callback with ID token (from frontend)
     */
    public function googleCallback(Request $request)
    {
        try {
            $token = $request->input('token');

            if (!$token) {
                return response()->json([
                    'success' => false,
                    'message' => 'Token is required'
                ], 400);
            }

            // Verify Google token
            $client = new GoogleClient(['client_id' => config('services.google.client_id')]);
            $payload = $client->verifyIdToken($token);

            if (!$payload) {
                return response()->json([
                    'success' => false,
                    'message' => 'Invalid token'
                ], 401);
            }

            // Get user info from token
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $picture = $payload['picture'] ?? null;

            // Find or create user
            $user = User::updateOrCreate(
                ['google_id' => $googleId],
                [
                    'name' => $name,
                    'email' => $email,
                    'picture' => $picture,
                    'provider' => 'google',
                    'email_verified_at' => now(),
                ]
            );

            // Create Sanctum token
            $token = $user->createToken('auth_token')->plainTextToken;

            // Create default board if user is new
            if ($user->boards()->count() === 0) {
                $board = $user->boards()->create([
                    'title' => 'My Board',
                    'description' => 'Default board'
                ]);

                // Create default columns
                $columns = [
                    ['title' => 'Todo', 'color' => '#E8EAF6', 'position' => 0],
                    ['title' => 'In Progress', 'color' => '#E3F2FD', 'position' => 1],
                    ['title' => 'Done', 'color' => '#E0F2F1', 'position' => 2],
                ];

                foreach ($columns as $column) {
                    $board->columns()->create($column);
                }
            }

            return response()->json([
                'success' => true,
                'message' => 'Login successful',
                'data' => [
                    'user' => $user,
                    'token' => $token,
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Authentication failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get user by ID
     */
    public function getUser(Request $request)
    {
        try {
            $userId = $request->query('userId');

            if (!$userId) {
                return response()->json([
                    'success' => false,
                    'message' => 'User ID is required'
                ], 400);
            }

            $user = User::find($userId);

            if (!$user) {
                return response()->json([
                    'success' => false,
                    'message' => 'User not found'
                ], 404);
            }

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to get user: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Logout user
     */
    public function logout(Request $request)
    {
        try {
            $request->user()->currentAccessToken()->delete();

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Logout failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get authenticated user
     */
    public function me(Request $request)
    {
        return response()->json([
            'success' => true,
            'data' => [
                'user' => $request->user()
            ]
        ]);
    }
}
