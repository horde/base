<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Auth\Jwt\GeneratedJwt;
use Horde_Registry;
use Psr\Log\LoggerInterface;
use Exception;
use Horde;

/**
 * Dual-mode Authentication Service
 *
 * Provides both traditional session-based authentication and modern JWT-based authentication.
 * After successful login, issues both a session cookie (for traditional UI) and a JWT token
 * (for responsive UI and API access).
 *
 * ## Hybrid Architecture: JWT for Identity, Session for Credentials
 *
 * This implementation uses a hybrid approach:
 * - **JWT tokens**: Prove user identity, contain claims (user_id, apps, permissions)
 * - **Session**: Stores sensitive credentials (passwords) needed by backend services
 *
 * ### Why Session + JWT?
 *
 * Horde applications (webmail, calendar, etc.) connect to backend services (IMAP, SMTP,
 * CalDAV) using the user's credentials. For truly stateless JWT-only auth, we would need
 * to either:
 * 1. Store encrypted password in JWT (increases token size, security risk)
 * 2. Require password on every backend operation (impractical)
 * 3. Use service accounts (not available in all deployments)
 *
 * **Solution**: Use PHP sessions to store credentials for backend services, while JWT
 * tokens prove identity and carry authorization claims. This allows:
 * - Stateless API authentication (JWT validates without database lookup)
 * - Backend services access user credentials from session
 * - Mobile/SPA apps use JWT, traditional UI uses session cookie
 * - Single login flow supports both modes
 *
 * ### Security Model
 *
 * - JWT tokens expire (1 hour default) and must be refreshed
 * - Credentials in session are encrypted by PHP session handling
 * - Session lifetime independent of JWT expiry
 * - Logout destroys session (but JWT remains valid until expiry)
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class AuthenticationService
{
    /**
     * @param Horde_Registry $registry Horde registry
     * @param LoggerInterface $logger PSR-3 logger
     * @param JwtService|null $jwtService JWT service (optional, for dual-mode)
     */
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly LoggerInterface $logger,
        private readonly ?JwtService $jwtService = null,
    ) {}

    /**
     * Authenticate a user with username and password
     *
     * Performs traditional Horde authentication and optionally generates JWT tokens.
     *
     * @param string $username Username
     * @param string $password Password
     * @param array $options Authentication options:
     *   - 'generate_jwt' => bool (default: true) - Generate JWT tokens
     *   - 'jwt_aud' => string|array - JWT audience claim
     *   - 'jwt_claims' => array - Additional JWT claims
     *   - 'mode' => string - View mode (auto, dynamic, smartmobile, mobile, basic)
     *   - 'auth_params' => array - Additional auth parameters to pass to auth backend
     * @return array Authentication result:
     *   - 'success' => bool
     *   - 'user_id' => string (on success)
     *   - 'session_id' => string (on success, if session created)
     *   - 'access_token' => string (on success, if JWT enabled)
     *   - 'refresh_token' => string (on success, if JWT enabled)
     *   - 'expires_at' => int (on success, if JWT enabled)
     *   - 'error' => string (on failure)
     */
    public function authenticate(string $username, string $password, array $options = []): array
    {
        $generateJwt = $options['generate_jwt'] ?? ($this->jwtService !== null);

        try {
            // Authenticate with the underlying auth system first
            $auth = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Auth')->create();

            // Build auth credentials array
            $authCredentials = ['password' => $password];

            // Add view mode if specified
            if (isset($options['mode'])) {
                $authCredentials['mode'] = $options['mode'];
            }

            // Merge any additional auth parameters
            if (isset($options['auth_params']) && is_array($options['auth_params'])) {
                $authCredentials = array_merge($authCredentials, $options['auth_params']);
            }

            $this->logger->debug('Authentication attempt', [
                'username' => $username,
                'generate_jwt' => $generateJwt,
                'has_jwt_service' => $this->jwtService !== null,
            ]);
            $authResult = $auth->authenticate($username, $authCredentials);
            $this->logger->debug('Authentication result', [
                'username' => $username,
                'success' => $authResult,
            ]);

            // Check if authentication actually succeeded
            if (!$authResult) {
                $this->logger->error('Authentication failed', [
                    'username' => $username,
                    'reason' => 'auth_backend_returned_false',
                ]);
                return [
                    'success' => false,
                    'error' => 'Authentication failed',
                ];
            }

            // Get full credentials from auth object (may include more than just password)
            $credentials = $auth->getCredential('credentials') ?: ['password' => $password];
            $userId = $username;

            // If JWT enabled, generate tokens
            if ($generateJwt && $this->jwtService !== null) {
                $this->logger->debug('Generating JWT tokens', [
                    'username' => $username,
                ]);

                // Generate JWT tokens
                $jwtClaims = $this->buildJwtClaims($username, $options);
                $refreshToken = $this->jwtService->generateRefreshToken($userId);
                $jti = $refreshToken->getClaim('jti');

                // Use normal session - middleware will have set ID to JTI if cookie present
                // On first login, session will have random ID (that's OK)
                // On subsequent requests, middleware sets session ID = JTI from cookie
                if (session_status() !== PHP_SESSION_ACTIVE) {
                    session_start();
                }

                // Store credentials in session
                $this->registry->setAuth($username, $credentials);

                // Generate access token (includes refresh_jti for session lookup)
                $accessToken = $this->jwtService->generateAccessToken($userId, array_merge(
                    $jwtClaims,
                    ['refresh_jti' => $jti]  // Link access token to session
                ));

                $this->logger->debug('JWT tokens generated', [
                    'username' => $username,
                    'session_id' => session_id(),
                    'jti' => $jti,
                    'access_token_expires_at' => $accessToken->expiresAt,
                ]);

                return [
                    'success' => true,
                    'user_id' => $userId,
                    'session_id' => session_id(),  // Actual session ID (may not be JTI on first login)
                    'access_token' => $accessToken->token,
                    'refresh_token' => $refreshToken->token,
                    'expires_at' => $accessToken->expiresAt,
                    'expires_in' => 900,
                    'token_type' => 'Bearer',
                ];
            }

            // Traditional authentication (no JWT)
            // Use existing session or create new one with PHP-generated ID
            if (session_status() !== PHP_SESSION_ACTIVE) {
                session_start();
            }
            $this->registry->setAuth($username, $credentials);
            $sessionId = session_id();

            return [
                'success' => true,
                'user_id' => $userId,
                'session_id' => $sessionId,
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Verify authentication from request
     *
     * Checks for JWT token first (modern), falls back to session (traditional).
     *
     * @param array $requestAttributes Request attributes from PSR-7 request
     * @return array Verification result:
     *   - 'authenticated' => bool
     *   - 'user_id' => string (if authenticated)
     *   - 'auth_type' => string ('jwt' or 'session')
     *   - 'claims' => array (if JWT authenticated)
     */
    public function verifyFromRequest(array $requestAttributes): array
    {
        // Check for JWT authentication
        if (isset($requestAttributes['jwt']) && isset($requestAttributes['jwt_user_id'])) {
            return [
                'authenticated' => true,
                'user_id' => $requestAttributes['jwt_user_id'],
                'auth_type' => 'jwt',
                'claims' => $requestAttributes['jwt_claims'] ?? [],
            ];
        }

        // Fall back to session authentication
        if ($this->registry->getAuth()) {
            return [
                'authenticated' => true,
                'user_id' => $this->registry->getAuth(),
                'auth_type' => 'session',
            ];
        }

        return [
            'authenticated' => false,
        ];
    }

    /**
     * Refresh JWT access token using refresh token
     *
     * Uses refresh token JTI to load the session. The JTI IS the session ID.
     *
     * @param string $refreshToken Valid refresh token
     * @return array Result:
     *   - 'success' => bool
     *   - 'access_token' => string (on success)
     *   - 'expires_at' => int (on success)
     *   - 'error' => string (on failure)
     */
    public function refreshToken(string $refreshToken): array
    {
        if ($this->jwtService === null) {
            return [
                'success' => false,
                'error' => 'JWT service not available',
            ];
        }

        try {
            // Step 1: Verify JWT signature and expiry
            $verified = $this->jwtService->verifyRefreshToken($refreshToken);
            $jti = $verified->getClaim('jti');
            $username = $verified->getClaim('sub');

            // Step 2: Load session using JTI (JTI IS the session ID)
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_write_close();
            }
            session_id($jti);
            session_start();

            // Step 3: Check session authenticated
            $currentUser = $this->registry->getAuth();
            if (!$currentUser) {
                // Destroy empty session to prevent file pollution.
                // When session_start() loads a non-existent session file, PHP
                // creates a new empty session that would be written back on close.
                // This prevents accumulation of empty session files.
                //
                // NOTE: For high-volume sites, consider making this cleanup
                // optional via configuration if session_destroy() performance
                // becomes a concern.
                session_destroy();

                return [
                    'success' => false,
                    'error' => 'Session expired, please re-login',
                ];
            }

            // Step 4: Verify username matches session
            if ($username !== $currentUser) {
                return [
                    'success' => false,
                    'error' => 'Token username does not match session',
                ];
            }

            // Step 5: Verify session ID matches JTI (prevents token reuse)
            $actualSessionId = session_id();
            if ($actualSessionId !== $jti) {
                return [
                    'success' => false,
                    'error' => 'Token not bound to this session',
                ];
            }

            // Step 6: Generate new access token (includes refresh_jti)
            $accessToken = $this->jwtService->generateAccessToken($username, [
                'refresh_jti' => $jti,
            ]);

            return [
                'success' => true,
                'access_token' => $accessToken->token,
                'expires_at' => $accessToken->expiresAt,
                'expires_in' => 900,
                'token_type' => 'Bearer',
            ];
        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log out a user
     *
     * Destroys session. Since session ID = refresh token JTI, destroying
     * the session automatically invalidates the refresh token.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->registry->clearAuth();
        // Session destroyed, tokens automatically invalid
    }

    /**
     * Issue JWT tokens for an already-authenticated user
     *
     * Used when user is authenticated via session but needs JWT tokens
     * (e.g., logged in via old login.php, now accessing modern UI).
     *
     * Creates new session with refresh token JTI as session ID, migrating
     * credentials from old session.
     *
     * @param string $username Authenticated username
     * @param array $options Optional JWT options (audience, claims)
     * @return array ['access_token' => ..., 'refresh_token' => ..., 'expires_in' => ...]
     * @throws Exception If JWT not configured or user not authenticated
     */
    public function issueTokensForAuthenticatedUser(string $username, array $options = []): array
    {
        if ($this->jwtService === null) {
            throw new Exception('JWT authentication not configured');
        }

        // Verify user is authenticated
        if ($this->registry->getAuth() !== $username) {
            throw new Exception('User not authenticated');
        }

        // Get current session data (credentials) before switching sessions
        $credentials = $_SESSION['__horde']['auth']['credentials'] ?? null;
        if (!$credentials) {
            throw new Exception('No credentials in session');
        }

        // Generate refresh token (JTI will be new session ID)
        $claims = $this->buildJwtClaims($username, $options);
        $refreshToken = $this->jwtService->generateRefreshToken($username);
        $jti = $refreshToken->getClaim('jti');

        // Switch to new session with JTI as ID
        session_write_close();
        session_id($jti);
        session_start();

        // Re-establish authentication in new session
        $this->registry->setAuth($username, $credentials);

        // Generate access token (includes refresh_jti)
        $accessToken = $this->jwtService->generateAccessToken($username, array_merge(
            $claims,
            ['refresh_jti' => $jti]
        ));

        return [
            'access_token' => $accessToken->token,
            'refresh_token' => $refreshToken->token,
            'expires_in' => 900,
            'token_type' => 'Bearer',
        ];
    }


    /**
     * Build JWT claims from user information
     *
     * @param string $username Username
     * @param array $options Options containing jwt_aud and jwt_claims
     * @return array JWT claims
     */
    private function buildJwtClaims(string $username, array $options): array
    {
        $claims = $options['jwt_claims'] ?? [];

        // Add audience if specified
        if (isset($options['jwt_aud'])) {
            $claims['aud'] = $options['jwt_aud'];
        }

        // NOTE: We intentionally do NOT include apps list or user preferences
        // in JWT claims because:
        // 1. Information disclosure: JWTs are base64-encoded, not encrypted
        // 2. Token bloat: Increases network overhead unnecessarily
        // 3. Stale data: Changes to apps/prefs don't invalidate existing tokens
        // 4. Unused: Controllers call $registry->listApps() directly anyway
        //
        // Keep JWT claims minimal - only identity and standard claims.

        return $claims;
    }

    /**
     * Check if JWT authentication is available
     *
     * @return bool
     */
    public function hasJwtSupport(): bool
    {
        return $this->jwtService !== null;
    }
}
