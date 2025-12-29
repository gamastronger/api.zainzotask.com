# Google OAuth Session Fix - Complete Documentation

## Changes Made

### 1. AuthController.php - Session Handling Fix

#### handleGoogleCallback()
- **Changed**: `Auth::login()` → `Auth::guard('web')->login($user, true)`
- **Added**: `$request->session()->regenerate()`
- **Added**: Comprehensive logging with session_id, auth check, user details
- **Why**: Explicit web guard usage + session regeneration ensures proper session establishment

#### me()
- **Added**: Try-catch wrapper to prevent 500 errors
- **Added**: 15+ logging data points:
  - Session ID
  - Session existence check
  - Auth check status (✓ or ✗)
  - Guard information
  - User ID
  - Cookie names
  - Request headers (Origin, Referer)
- **Fixed**: Returns 401 if not authenticated (instead of 500)
- **Fixed**: Returns 500 with error details only on exceptions

#### logout()
- **Changed**: `Auth::logout()` → `Auth::guard('web')->logout()`
- **Added**: Enhanced error handling
- **Added**: User email logging for audit trail

### 2. routes/api.php - Debug Endpoint

Added `/debug/auth` endpoint that returns:
- session_id
- has_session (boolean)
- session_data (full array)
- auth_check (boolean)
- auth_id
- auth_user (full object)
- guard (string)
- web_guard_check (boolean)
- web_guard_id
- cookies (array of cookie names)
- request_user

**⚠️ Remove this endpoint before production deployment!**

## Testing Instructions

### Step 1: Clear Browser State
```
1. Open browser DevTools (F12)
2. Go to Application/Storage tab
3. Clear all cookies for localhost:8000 and localhost:5173
4. Clear all local storage
```

### Step 2: Test OAuth Flow
```
1. Visit: http://localhost:5173 (your frontend)
2. Click "Login with Google"
3. Complete Google OAuth
4. Should redirect back to frontend
```

### Step 3: Check Logs
```powershell
# Open Laravel logs
Get-Content c:\dev\zainzo-backend\storage\logs\laravel.log -Tail 50 -Wait
```

Look for these log entries:
- `✓ User authenticated after login:`
- `→ /api/auth/me called`
- `✓ User authenticated:` (in me() method)

### Step 4: Test /api/auth/me
```javascript
// In browser console on localhost:5173
fetch('http://localhost:8000/api/auth/me', {
  credentials: 'include'
})
.then(r => r.json())
.then(console.log)
```

**Expected Result**: 200 OK with user data

### Step 5: Check Debug Endpoint (if issues persist)
```javascript
fetch('http://localhost:8000/api/debug/auth', {
  credentials: 'include'
})
.then(r => r.json())
.then(console.log)
```

**What to check**:
- `has_session` should be `true`
- `auth_check` should be `true`
- `web_guard_check` should be `true`
- `auth_user` should contain user data
- `cookies` should include `laravel_session`

## Configuration Checklist

### ✅ Session Configuration (config/session.php)
- Driver: `cookie`
- Domain: `null` (allows cookies to work across localhost ports)
- Path: `/`
- Secure: `false` (for local development)
- Same Site: `lax`

### ✅ Sanctum Configuration (config/sanctum.php)
- Stateful domains: `localhost:5173,localhost:3000,localhost,127.0.0.1:5173,127.0.0.1:8000`
- Guard: `['web']`

### ✅ CORS Configuration (config/cors.php)
- Paths: `['api/*', 'sanctum/csrf-cookie', 'auth/*']`
- Allowed Origins: `['http://localhost:5173']`
- Supports Credentials: `true`

### ✅ Auth Configuration (config/auth.php)
- Default guard: `web`
- Web guard driver: `session`
- Session provider: `users`

### ✅ Middleware Configuration
- OAuth routes in `routes/web.php` (have web middleware)
- API routes use `auth:sanctum` middleware
- `EnsureFrontendRequestsAreStateful` in api middleware group

## Environment Variables

Ensure these are set in `.env`:

```env
SESSION_DRIVER=cookie
SESSION_LIFETIME=120
SESSION_DOMAIN=null
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

SANCTUM_STATEFUL_DOMAINS=localhost:5173,localhost:3000,localhost,127.0.0.1:5173,127.0.0.1:8000
```

## Common Issues & Solutions

### Issue 1: Still Getting 500 Error
**Diagnosis**: Check logs for exception details
**Solution**: The try-catch in me() should prevent 500s. If still happening, check:
- PHP error logs
- Laravel logs for exception stack traces

### Issue 2: Getting 401 Unauthorized
**Diagnosis**: Session not persisting
**Solution**: 
1. Check `/debug/auth` endpoint - verify `has_session` and `auth_check`
2. Verify cookies are being sent (check DevTools Network tab)
3. Ensure frontend uses `credentials: 'include'`

### Issue 3: Session ID Changes Between Requests
**Diagnosis**: Session not being recognized
**Solution**:
1. Verify `SESSION_DOMAIN` is `null` in `.env`
2. Check Sanctum stateful domains include your frontend URL
3. Ensure CORS `supports_credentials` is `true`

### Issue 4: User Data Null After Login
**Diagnosis**: Auth::login() not working
**Solution**:
1. Check logs after OAuth callback - should see "✓ User authenticated after login"
2. Verify `Auth::guard('web')->login($user, true)` is being called
3. Check `session()->regenerate()` is called after login

## Frontend Requirements

Your frontend should:

1. **Include credentials in all requests**:
```javascript
fetch('http://localhost:8000/api/auth/me', {
  credentials: 'include'
})
```

2. **Handle CORS properly**:
```javascript
axios.defaults.withCredentials = true;
```

3. **Get CSRF token before login** (optional but recommended):
```javascript
await fetch('http://localhost:8000/sanctum/csrf-cookie', {
  credentials: 'include'
});
```

## Production Deployment Checklist

Before deploying to production:

1. ✅ Remove `/debug/auth` endpoint from `routes/api.php`
2. ✅ Set `SESSION_SECURE_COOKIE=true` in production `.env`
3. ✅ Set `SESSION_DOMAIN=.yourdomain.com` (note the leading dot)
4. ✅ Update `SANCTUM_STATEFUL_DOMAINS` to production domains
5. ✅ Update CORS `allowed_origins` to production frontend URL
6. ✅ Remove or reduce verbose logging in AuthController
7. ✅ Enable HTTPS on both frontend and backend

## Architecture Overview

```
┌─────────────────┐
│   Frontend      │
│ (localhost:5173)│
└────────┬────────┘
         │ 1. Click "Login with Google"
         ▼
┌─────────────────┐
│ GET /api/auth/  │
│     google      │◄─── web middleware (sessions, cookies)
└────────┬────────┘
         │ 2. Redirect to Google
         ▼
┌─────────────────┐
│  Google OAuth   │
│    Login Page   │
└────────┬────────┘
         │ 3. User authenticates
         ▼
┌─────────────────┐
│ GET /api/auth/  │
│ google/callback │◄─── web middleware
│                 │
│ → Auth::guard   │
│   ('web')->     │
│   login($user)  │
│ → session()->   │
│   regenerate()  │
└────────┬────────┘
         │ 4. Session created + cookie set
         ▼
┌─────────────────┐
│   Frontend      │
│  (OAuth success)│
└────────┬────────┘
         │ 5. Fetch with credentials: 'include'
         ▼
┌─────────────────┐
│ GET /api/auth/me│◄─── auth:sanctum middleware
│                 │◄─── EnsureFrontendRequestsAreStateful
│ → Check session │
│ → Return user   │
└─────────────────┘
```

## Key Technical Details

### Why Auth::guard('web')->login()?
- Laravel has multiple guards (web, api, sanctum)
- Explicit guard usage ensures we're using the session driver
- Prevents confusion between token-based and session-based auth

### Why session()->regenerate()?
- Security: Prevents session fixation attacks
- Reliability: Ensures fresh session after state change
- Best practice: Laravel docs recommend regenerating after login

### Why EnsureFrontendRequestsAreStateful?
- Sanctum middleware that enables session auth for SPAs
- Checks if request comes from stateful domain
- Allows session-based auth for API routes

### Why web middleware for OAuth routes?
- OAuth callback needs to set session cookies
- Web middleware provides session, cookies, CSRF protection
- API middleware is for authenticated API calls, not OAuth flow

## Success Indicators

When everything works correctly, you should see:

1. **Logs**: ✓ symbols after OAuth callback and on /api/auth/me calls
2. **Network**: 200 OK on /api/auth/me
3. **Cookies**: `laravel_session` cookie present in browser
4. **Response**: User data returned from /api/auth/me
5. **Debug endpoint**: All checks return `true`

---

**Last Updated**: 2024-01-XX (after comprehensive session fix)
**Status**: Ready for testing
