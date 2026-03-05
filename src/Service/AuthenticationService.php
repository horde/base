<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Auth\Jwt\GeneratedJwt;
use Horde_Registry;

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
     * @param JwtService|null $jwtService JWT service (optional, for dual-mode)
     */
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly ?JwtService $jwtService = null
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
            $auth->authenticate($username, ['password' => $password]);

            // Store credentials in session for backend services (IMAP, SMTP, etc.)
            // This is crucial for stateless JWT auth - the session holds credentials
            // that backends need, while JWT proves identity
            $credentials = ['password' => $password];

            // Perform traditional Horde authentication with credentials
            $this->registry->setAuth($username, $credentials);

            // Get user information
            $userId = $username;
            $sessionId = session_id();

            $result = [
                'success' => true,
                'user_id' => $userId,
            ];

            if ($sessionId) {
                $result['session_id'] = $sessionId;
            }

            // Generate JWT tokens if enabled
            if ($generateJwt && $this->jwtService !== null) {
                $jwtClaims = $this->buildJwtClaims($username, $options);
                $accessToken = $this->jwtService->generateAccessToken($userId, $jwtClaims);
                $refreshToken = $this->jwtService->generateRefreshToken($userId, $accessToken->token);

                $result['access_token'] = $accessToken->token;
                $result['refresh_token'] = $refreshToken->token;
                $result['expires_at'] = $accessToken->expiresAt;
                $result['token_type'] = 'Bearer';
            }

            return $result;
        } catch (\Exception $e) {
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
            $accessToken = $this->jwtService->refreshAccessToken($refreshToken);

            return [
                'success' => true,
                'access_token' => $accessToken->token,
                'expires_at' => $accessToken->expiresAt,
                'token_type' => 'Bearer',
            ];
        } catch (\Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage(),
            ];
        }
    }

    /**
     * Log out a user
     *
     * Destroys session. Note: JWT tokens cannot be "revoked" without a blacklist
     * (they remain valid until expiry). For production, implement token blacklist
     * using jti claim.
     *
     * @return void
     */
    public function logout(): void
    {
        $this->registry->clearAuth();
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

        // Try to get user's apps from registry
        try {
            $apps = $this->registry->listApps();
            if (!empty($apps)) {
                $claims['apps'] = $apps;
            }
        } catch (\Exception $e) {
            // Ignore - apps will not be in claims
        }

        // Try to get user's language preference
        try {
            $prefs = $GLOBALS['injector']->getInstance('Horde_Core_Factory_Prefs')->create();
            $lang = $prefs->getValue('language');
            if ($lang) {
                $claims['lang'] = $lang;
            }
        } catch (\Exception $e) {
            // Ignore - language will not be in claims
        }

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
