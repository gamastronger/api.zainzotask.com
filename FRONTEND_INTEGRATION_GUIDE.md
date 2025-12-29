# 🎯 Frontend Integration Guide - Quick Reference

## ✅ THE FIX IS COMPLETE

The backend authentication is now **session-based** using Laravel Sanctum's stateful authentication. Here's what the frontend needs to do:

---

## 📋 REQUIRED FRONTEND CHANGES

### 1. **Before ANY Authentication - Get CSRF Token**

```javascript
// Call this ONCE when app loads (before login)
async function initializeAuth() {
  try {
    await fetch('http://localhost:8000/sanctum/csrf-cookie', {
      credentials: 'include'  // REQUIRED
    });
    console.log('CSRF token initialized');
  } catch (error) {
    console.error('Failed to initialize CSRF:', error);
  }
}

// Call on app mount
initializeAuth();
```

### 2. **Login Button**

```javascript
function handleGoogleLogin() {
  // Simply redirect to backend OAuth endpoint
  window.location.href = 'http://localhost:8000/api/auth/google';
}
```

### 3. **Success Page Handler** (`/auth/success`)

```javascript
// This runs after OAuth redirect from backend
async function handleAuthSuccess() {
  try {
    // Fetch user data - session cookie sent automatically
    const response = await fetch('http://localhost:8000/api/auth/me', {
      credentials: 'include',  // CRITICAL
      headers: {
        'Accept': 'application/json'
      }
    });

    if (!response.ok) {
      throw new Error('Not authenticated');
    }

    const data = await response.json();
    
    if (data.success && data.data.user) {
      // Store user in state/context
      localStorage.setItem('user', JSON.stringify(data.data.user));
      
      // Redirect to dashboard
      window.location.href = '/dashboard';
    } else {
      throw new Error('Invalid response');
    }
    
  } catch (error) {
    console.error('Auth verification failed:', error);
    window.location.href = '/auth/error?reason=verification_failed';
  }
}

// Run immediately when page loads
handleAuthSuccess();
```

### 4. **Error Page Handler** (`/auth/error`)

```javascript
function handleAuthError() {
  const params = new URLSearchParams(window.location.search);
  const reason = params.get('reason');
  
  const errorMessages = {
    'configuration_error': 'OAuth configuration error',
    'access_denied': 'You denied access to your Google account',
    'no_code': 'Authorization failed',
    'token_exchange_failed': 'Failed to complete authentication',
    'no_id_token': 'Authentication incomplete',
    'invalid_token': 'Invalid authentication token',
    'server_error': 'Server error occurred',
    'verification_failed': 'Session verification failed'
  };
  
  const message = errorMessages[reason] || 'Authentication failed';
  
  // Display error to user
  console.error('Auth error:', reason, message);
  
  // Show error message in UI
  document.getElementById('error-message').textContent = message;
}
```

### 5. **All Authenticated Requests**

```javascript
// Example: Fetch boards
async function fetchBoards() {
  const response = await fetch('http://localhost:8000/api/boards', {
    credentials: 'include',  // MUST INCLUDE
    headers: {
      'Accept': 'application/json'
    }
  });
  
  if (!response.ok) {
    if (response.status === 401) {
      // Session expired - redirect to login
      window.location.href = '/login';
      return;
    }
    throw new Error('Failed to fetch boards');
  }
  
  return await response.json();
}

// Example: Create board
async function createBoard(title, description) {
  const response = await fetch('http://localhost:8000/api/boards', {
    method: 'POST',
    credentials: 'include',  // MUST INCLUDE
    headers: {
      'Accept': 'application/json',
      'Content-Type': 'application/json'
    },
    body: JSON.stringify({ title, description })
  });
  
  if (!response.ok) {
    if (response.status === 401) {
      window.location.href = '/login';
      return;
    }
    throw new Error('Failed to create board');
  }
  
  return await response.json();
}
```

### 6. **Logout**

```javascript
async function logout() {
  try {
    await fetch('http://localhost:8000/api/auth/logout', {
      method: 'POST',
      credentials: 'include',  // MUST INCLUDE
      headers: {
        'Accept': 'application/json'
      }
    });
  } catch (error) {
    console.error('Logout error:', error);
  } finally {
    // Clear local storage regardless
    localStorage.clear();
    sessionStorage.clear();
    
    // Redirect to login
    window.location.href = '/login';
  }
}
```

### 7. **Check Authentication Status**

```javascript
// Use this to check if user is authenticated
async function checkAuth() {
  try {
    const response = await fetch('http://localhost:8000/api/auth/me', {
      credentials: 'include',
      headers: {
        'Accept': 'application/json'
      }
    });
    
    if (response.ok) {
      const data = await response.json();
      return data.success ? data.data.user : null;
    }
    
    return null;
  } catch (error) {
    console.error('Auth check failed:', error);
    return null;
  }
}

// Example: Protected route guard
async function guardProtectedRoute() {
  const user = await checkAuth();
  
  if (!user) {
    window.location.href = '/login';
    return false;
  }
  
  return true;
}
```

---

## 🚨 CRITICAL RULES

### ✅ DO's

1. ✅ **Always use `credentials: 'include'`** in every fetch request
2. ✅ Call `/sanctum/csrf-cookie` before first authentication
3. ✅ Use `http://localhost:8000` (full URL) for all API calls
4. ✅ Check for 401 responses and redirect to login
5. ✅ Handle errors gracefully

### ❌ DON'Ts

1. ❌ Don't use `Authorization: Bearer` header
2. ❌ Don't try to manually get/store tokens
3. ❌ Don't send credentials in URL parameters
4. ❌ Don't skip `credentials: 'include'`
5. ❌ Don't use relative URLs for API calls

---

## 🔍 DEBUGGING

### Check Session Cookie

**Browser DevTools → Application → Cookies → http://localhost:8000**

You should see:
- Cookie name: `laravel_session` or `zainzo-backend-session`
- HttpOnly: ✅ Yes
- Secure: ❌ No (localhost)
- SameSite: Lax

### Check Network Requests

**DevTools → Network → Select Request → Headers Tab**

**Request Headers should include:**
```
Cookie: laravel_session=eyJpdiI6I...
Accept: application/json
```

**Response Headers should include:**
```
Set-Cookie: laravel_session=eyJpdiI6I...; path=/; domain=localhost; httponly
```

### Common Issues

**Issue: 401 Unauthorized on /api/auth/me**

```javascript
// Solution 1: Ensure credentials are included
fetch('http://localhost:8000/api/auth/me', {
  credentials: 'include'  // ← MUST HAVE THIS
});

// Solution 2: Check CSRF cookie was fetched
await fetch('http://localhost:8000/sanctum/csrf-cookie', {
  credentials: 'include'
});
```

**Issue: CORS errors**

```javascript
// ✅ CORRECT: Full URL with credentials
fetch('http://localhost:8000/api/auth/me', {
  credentials: 'include'
});

// ❌ WRONG: Relative URL
fetch('/api/auth/me');  // Won't work with CORS

// ❌ WRONG: Missing credentials
fetch('http://localhost:8000/api/auth/me');
```

**Issue: Session not persisting**

```javascript
// Check browser console for cookie warnings
// Ensure you're not in incognito/private mode
// Check browser settings allow third-party cookies
```

---

## 📝 EXAMPLE: Complete Auth Flow

```javascript
// 1. App initialization
async function initApp() {
  // Get CSRF token
  await fetch('http://localhost:8000/sanctum/csrf-cookie', {
    credentials: 'include'
  });
  
  // Check if already authenticated
  const user = await checkAuth();
  
  if (user) {
    // User is logged in, show dashboard
    loadDashboard(user);
  } else {
    // Not logged in, show login page
    showLoginPage();
  }
}

// 2. Login page
function LoginPage() {
  return (
    <button onClick={() => {
      window.location.href = 'http://localhost:8000/api/auth/google';
    }}>
      Sign in with Google
    </button>
  );
}

// 3. Auth success page
function AuthSuccessPage() {
  useEffect(() => {
    async function verifyAuth() {
      try {
        const response = await fetch('http://localhost:8000/api/auth/me', {
          credentials: 'include',
          headers: { 'Accept': 'application/json' }
        });
        
        if (response.ok) {
          const data = await response.json();
          if (data.success) {
            localStorage.setItem('user', JSON.stringify(data.data.user));
            window.location.href = '/dashboard';
            return;
          }
        }
        
        throw new Error('Auth verification failed');
      } catch (error) {
        console.error(error);
        window.location.href = '/auth/error?reason=verification_failed';
      }
    }
    
    verifyAuth();
  }, []);
  
  return <div>Verifying authentication...</div>;
}

// 4. Dashboard (protected)
function Dashboard() {
  const [boards, setBoards] = useState([]);
  
  useEffect(() => {
    async function loadBoards() {
      try {
        const response = await fetch('http://localhost:8000/api/boards', {
          credentials: 'include',
          headers: { 'Accept': 'application/json' }
        });
        
        if (!response.ok) {
          if (response.status === 401) {
            window.location.href = '/login';
            return;
          }
          throw new Error('Failed to load boards');
        }
        
        const data = await response.json();
        setBoards(data.data || []);
      } catch (error) {
        console.error('Error loading boards:', error);
      }
    }
    
    loadBoards();
  }, []);
  
  return (
    <div>
      <h1>My Boards</h1>
      {boards.map(board => (
        <div key={board.id}>{board.title}</div>
      ))}
      
      <button onClick={async () => {
        await fetch('http://localhost:8000/api/auth/logout', {
          method: 'POST',
          credentials: 'include'
        });
        localStorage.clear();
        window.location.href = '/login';
      }}>
        Logout
      </button>
    </div>
  );
}
```

---

## ✅ TESTING CHECKLIST

1. [ ] CSRF cookie fetched on app load
2. [ ] Login redirects to Google OAuth
3. [ ] Google redirects back to /auth/success
4. [ ] /auth/success calls /api/auth/me successfully
5. [ ] Dashboard loads with user data
6. [ ] API requests include session cookie
7. [ ] Logout clears session and redirects to login
8. [ ] 401 errors redirect to login page

---

## 🎉 EXPECTED RESULTS

After implementing these changes:

1. ✅ User can log in with Google
2. ✅ `/api/auth/me` returns 200 with user data
3. ✅ Dashboard loads successfully
4. ✅ All API requests work with session authentication
5. ✅ Logout clears session properly
6. ✅ No more 500 errors!

---

**STATUS: 🚀 READY FOR FRONTEND INTEGRATION**

Backend is fixed and waiting for these frontend changes!
