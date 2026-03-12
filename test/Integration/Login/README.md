# Login Parity Integration Tests

Tests that verify `login.php` and `/auth/login` produce identical behavior.

## Running Tests

```bash
cd /home/uid/git/horde/base

# Run all login parity tests
phpunit test/Integration/Login/LoginParityTest.php

# Run specific test
phpunit --filter testBothEndpointsShowLoginForm test/Integration/Login/LoginParityTest.php
```

## Test Credentials

Tests that require authentication need valid Horde credentials:

```bash
# Set via environment variables
export HORDE_TEST_USER=your_username
export HORDE_TEST_PASS=your_password

# Then run tests
phpunit test/Integration/Login/LoginParityTest.php
```

Or configure in `phpunit.xml`:

```xml
<php>
    <env name="HORDE_TEST_USER" value="testuser"/>
    <env name="HORDE_TEST_PASS" value="testpass"/>
</php>
```

## Test Coverage

### ✅ Implemented
- **TC1.1:** Both endpoints show login form ✅ PASSING
- **TC2.1:** Successful login with JWT generation ⏳ INCOMPLETE (JWT not enabled)
- **TC3.1:** Failed login without JWT ✅ HARMONIZED (both use PRG pattern)
- **TC3.2:** Empty credentials rejected ✅ HARMONIZED (both use PRG pattern)
- **TC4.1:** Language selection persists ✅ PASSING (both store in session)

### 📋 Planned (See planning document)
- TC1.2: Already authenticated user redirects
- TC1.3: Login page with redirect URL preservation
- TC4.1: Standard logout
- TC4.2: Session expiration
- TC5.1: 2FA valid code
- TC5.2: 2FA invalid code
- TC6.1: Language selection
- TC7.1: View mode selection
- TC8.1: Password change required

## What Tests Verify

### Login Form Display
- Both endpoints render login form with identical fields
- Username field (`horde_user`)
- Password field (`horde_pass`)
- Submit button

### Successful Login Behavior
- HTTP 302 redirect
- Redirect to portal or requested URL
- JWT refresh token set as HTTP-only cookie (if JWT enabled)
- JWT bootstrap data in session flash (if JWT enabled)

### Failed Login Behavior (✅ HARMONIZED - PRG Pattern)
**Both endpoints now use identical POST-Redirect-GET (PRG) pattern:**
- Returns HTTP 302 redirect on authentication failure
- Redirects to same endpoint with `?error=<code>` parameter
- Error codes: `badlogin`, `expired`, `locked`, `required`, `secondfactor`
- Error message displayed on GET request

**Benefits:**
- Prevents form resubmission on F5/refresh
- Keeps POST out of browser history
- Fresh CSRF token on redirect
- Industry standard security practice

### JWT Token Generation
- JWT refresh token set as HTTP-only cookie on success
- JWT bootstrap data in session flash on success
- Tokens have valid JWT format (3 parts)
- Tokens not generated on failed login

### Language Selection (✅ VERIFIED)
**Both endpoints handle language identically:**
- Language selected via `new_lang` parameter (e.g., `de_DE`)
- Stored in session via `$registry->setLanguageEnvironment()`
- Portal displays in selected language after login
- No cookie needed - session-based storage

**Test verification:**
- German text ("Kalender", "Abmelden") appears in portal after de_DE login
- Both endpoints produce identical behavior

## Architecture

Uses native Horde components only:
- `Horde\Http\Client\Curl` - PSR-18 HTTP client
- `Horde\Http\HordeClientWrapper` - Convenience wrapper
- `Horde\Http\RequestFactory` - PSR-7 request factory
- `Horde\Http\StreamFactory` - PSR-7 stream factory
- `Horde\Http\ResponseFactory` - PSR-7 response factory

No external dependencies (no Guzzle).

## Implementation Notes

### POST Form Handling
Uses `postForm()` helper to properly encode form data with `application/x-www-form-urlencoded` content type.

### Session Inspection
Reads PHP session files directly from file system to verify JWT bootstrap data.

### JWT Validation
Tests verify JWT **presence and structure**, not claims validation (out of scope).
