<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cookie;
use Google\Client as GoogleClient;

/**
 * AuthController - Backend-Driven Google OAuth Authentication
 *
 * AUTHENTICATION FLOW:
 * 1. Frontend redirects user to GET /api/auth/google
 * 2. Backend redirects to Google OAuth consent screen
 * 3. User authorizes, Google redirects to GET /api/auth/google/callback?code=...
 * 4. Backend exchanges code for tokens, verifies user, creates/updates user record
 * 5. Backend generates Sanctum token and stores it in HTTP-only cookie
 * 6. Backend redirects to frontend /auth/success (no sensitive data in URL)
 * 7. Frontend can now make authenticated requests with the cookie
 *
 * The backend is the single source of truth for authentication success/failure.
 */
class AuthController extends Controller
{
    /**
     * Step 1: Initiate OAuth flow by redirecting to Google
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function redirectToGoogle(Request $request)
    {
        $clientId = config('services.google.client_id');
        $redirectUri = config('services.google.redirect');

        // Validate configuration
        if (!$clientId || !$redirectUri) {
            Log::error('Google OAuth configuration missing');
            return redirect(config('app.frontend_url') . '/auth/error?reason=configuration_error');
        }

        // Build Google OAuth URL
        $params = [
            'client_id' => $clientId,
            'redirect_uri' => $redirectUri,
            'response_type' => 'code',
            'scope' => 'openid profile email',
            'access_type' => 'offline',
            'prompt' => 'consent',
        ];

        $authUrl = 'https://accounts.google.com/o/oauth2/v2/auth?' . http_build_query($params);

        return redirect($authUrl);
    }

    /**
     * Step 2: Handle Google OAuth callback
     *
     * Receives authorization code from Google, exchanges it for tokens,
     * verifies user identity, creates/updates user, generates Sanctum token,
     * and stores it in an HTTP-only cookie before redirecting to frontend.
     *
     * @param Request $request
     * @return \Illuminate\Http\RedirectResponse
     */
    public function handleGoogleCallback(Request $request)
    {
        try {
            // Check for errors from Google
            if ($request->has('error')) {
                Log::warning('Google OAuth error', ['error' => $request->input('error')]);
                return redirect(config('app.frontend_url') . '/auth/error?reason=access_denied');
            }

            // Get authorization code
            $code = $request->input('code');
            if (!$code) {
                Log::error('No authorization code received');
                return redirect(config('app.frontend_url') . '/auth/error?reason=no_code');
            }

            // Initialize Google Client and exchange code for tokens
            $client = new GoogleClient();
            $client->setClientId(config('services.google.client_id'));
            $client->setClientSecret(config('services.google.client_secret'));
            $client->setRedirectUri(config('services.google.redirect'));

            $token = $client->fetchAccessTokenWithAuthCode($code);

            if (isset($token['error'])) {
                Log::error('Token exchange failed', ['error' => $token['error']]);
                return redirect(config('app.frontend_url') . '/auth/error?reason=token_exchange_failed');
            }

            // Extract and verify ID token
            $idToken = $token['id_token'] ?? null;
            if (!$idToken) {
                Log::error('No ID token in response');
                return redirect(config('app.frontend_url') . '/auth/error?reason=no_id_token');
            }

            $payload = $client->verifyIdToken($idToken);
            if (!$payload) {
                Log::error('ID token verification failed');
                return redirect(config('app.frontend_url') . '/auth/error?reason=invalid_token');
            }

            // Extract user information from verified token
            $googleId = $payload['sub'];
            $email = $payload['email'];
            $name = $payload['name'];
            $picture = $payload['picture'] ?? null;

            // Create or update user in database
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

            // Create default board for new users
            if ($user->boards()->count() === 0) {
                $this->createDefaultBoard($user);
            }

            // Generate Sanctum authentication token
            $sanctumToken = $user->createToken('auth_token')->plainTextToken;

            Log::info('User authenticated successfully', ['user_id' => $user->id]);

            // Store token in HTTP-only cookie (secure, not accessible via JavaScript)
            $cookie = cookie(
                'auth_token',              // name
                $sanctumToken,             // value
                60 * 24 * 7,              // minutes (7 days)
                '/',                       // path
                config('session.domain'),  // domain
                true,                      // secure (HTTPS only)
                true,                      // httpOnly
                false,                     // raw
                'strict'                   // sameSite
            );

            // Redirect to frontend success page with cookie
            // No sensitive data in URL - frontend will call /api/auth/me with cookie
            return redirect(config('app.frontend_url') . '/auth/success')->cookie($cookie);

        } catch (\Exception $e) {
            Log::error('OAuth callback exception', [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);
            return redirect(config('app.frontend_url') . '/auth/error?reason=server_error');
        }
    }

    /**
     * Get authenticated user
     *
     * Returns the currently authenticated user's data.
     * Requires Sanctum token (from cookie or Authorization header).
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
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

    /**
     * Logout user
     *
     * Revokes the current Sanctum token and clears the auth cookie.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            // Revoke current access token
            $request->user()->currentAccessToken()->delete();

            Log::info('User logged out', ['user_id' => $request->user()->id]);

            // Clear the auth cookie
            $cookie = Cookie::forget('auth_token');

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ])->cookie($cookie);

        } catch (\Exception $e) {
            Log::error('Logout failed', ['error' => $e->getMessage()]);
            return response()->json([
                'success' => false,
                'message' => 'Logout failed'
            ], 500);
        }
    }

    /**
     * Create default board and columns for new user
     *
     * @param User $user
     * @return void
     */
    private function createDefaultBoard(User $user)
    {
        $board = $user->boards()->create([
            'title' => 'My Board',
            'description' => 'Default board'
        ]);

        $columns = [
            ['title' => 'Todo', 'color' => '#E8EAF6', 'position' => 0],
            ['title' => 'In Progress', 'color' => '#E3F2FD', 'position' => 1],
            ['title' => 'Done', 'color' => '#E0F2F1', 'position' => 2],
        ];

        foreach ($columns as $column) {
            $board->columns()->create($column);
        }
    }
}
