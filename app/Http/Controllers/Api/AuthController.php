<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
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

            // CRITICAL: Log the user into the web guard (session-based authentication)
            // This establishes the user's session which Sanctum will use for authentication
            Auth::guard('web')->login($user, true); // true = remember me

            // Regenerate session ID to prevent session fixation attacks
            $request->session()->regenerate();

            Log::info('✓ User authenticated and session established', [
                'user_id' => $user->id,
                'user_email' => $user->email,
                'session_id' => session()->getId(),
                'auth_check' => Auth::check(),
                'guard' => Auth::getDefaultDriver()
            ]);

            // Redirect to frontend success page
            // The Laravel session cookie is automatically set via web middleware
            // Frontend will call /api/auth/me with session cookie (via credentials: 'include')
            return redirect(config('app.frontend_url') . '/auth/success');

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
     * Uses session-based authentication via Sanctum's stateful guard.
     *
     * CRITICAL: This endpoint uses auth:sanctum middleware which checks:
     * 1. Session authentication (if request is from stateful domain)
     * 2. Bearer token authentication (fallback)
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function me(Request $request)
    {
        try {
            // Log incoming request details for debugging
            Log::info('→ /api/auth/me called', [
                'session_id' => session()->getId(),
                'has_session' => $request->hasSession(),
                'auth_check' => Auth::check(),
                'guard' => Auth::getDefaultDriver(),
                'user_id' => Auth::id(),
                'cookies' => array_keys($request->cookies->all()),
                'origin' => $request->header('Origin'),
                'referer' => $request->header('Referer')
            ]);

            // Get authenticated user via Sanctum middleware
            // The auth:sanctum middleware should have already authenticated the user
            $user = $request->user();

            if (!$user) {
                // This should rarely happen if middleware is working correctly
                Log::warning('✗ User not authenticated in /api/auth/me', [
                    'session_id' => session()->getId(),
                    'session_data' => session()->all(),
                    'auth_check' => Auth::check(),
                    'guard_web_check' => Auth::guard('web')->check(),
                ]);

                return response()->json([
                    'success' => false,
                    'message' => 'Unauthenticated'
                ], 401);
            }

            Log::info('✓ User authenticated successfully', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            return response()->json([
                'success' => true,
                'data' => [
                    'user' => $user
                ]
            ]);

        } catch (\Exception $e) {
            // Catch any unexpected errors to prevent 500 responses
            Log::error('✗ Exception in /api/auth/me', [
                'message' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Internal server error',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
            ], 500);
        }
    }

    /**
     * Logout user
     *
     * Logs out the user from the session and invalidates the session.
     *
     * @param Request $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function logout(Request $request)
    {
        try {
            $user = $request->user();

            if (!$user) {
                Log::warning('Logout attempt without authentication');
                return response()->json([
                    'success' => false,
                    'message' => 'Not authenticated'
                ], 401);
            }

            Log::info('User logging out', [
                'user_id' => $user->id,
                'user_email' => $user->email
            ]);

            // Log out from the web guard
            Auth::guard('web')->logout();

            // Invalidate the session
            $request->session()->invalidate();

            // Regenerate CSRF token
            $request->session()->regenerateToken();

            Log::info('✓ User logged out successfully');

            return response()->json([
                'success' => true,
                'message' => 'Logged out successfully'
            ]);

        } catch (\Exception $e) {
            Log::error('✗ Logout failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString()
            ]);

            return response()->json([
                'success' => false,
                'message' => 'Logout failed',
                'error' => config('app.debug') ? $e->getMessage() : 'An error occurred'
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
