<?php

declare(strict_types=1);

namespace Horde\Horde\Service;

use Horde\Core\Auth\Jwt\Hs256Generator;
use Horde\Core\Auth\Jwt\JwtVerifier;
use Horde\Core\Auth\Jwt\GeneratedJwt;
use Horde\Core\Auth\Jwt\VerifiedJwt;
use InvalidArgumentException;

/**
 * JWT Service for Horde authentication
 *
 * Handles JWT generation and verification for session tokens.
 * Works alongside traditional session-based authentication without breaking it.
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
class JwtService
{
    private Hs256Generator $generator;
    private JwtVerifier $verifier;

    /**
     * @param string $secret Shared secret for JWT signing (256-bit recommended)
     * @param string $issuer JWT issuer (e.g., 'horde.example.com')
     * @param int $accessTokenTtl Access token lifetime in seconds (default: 1 hour)
     * @param int $refreshTokenTtl Refresh token lifetime in seconds (default: 30 days)
     */
    public function __construct(
        private readonly string $secret,
        private readonly string $issuer,
        private readonly int $accessTokenTtl = 3600,
        private readonly int $refreshTokenTtl = 2592000
    ) {
        if (trim($secret) === '') {
            throw new InvalidArgumentException('JWT secret cannot be empty');
        }

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException('JWT secret must be at least 256 bits (32 bytes)');
        }

        $this->generator = new Hs256Generator();
        $this->verifier = new JwtVerifier();
    }

    /**
     * Generate an access token for a user
     *
     * @param string $userId User identifier (username or ID)
     * @param array $customClaims Additional claims to include:
     *   - 'aud' => string|array - Audience (apps the token is valid for)
     *   - 'apps' => array - List of accessible Horde apps
     *   - 'lang' => string - User's preferred language
     *   - 'mode' => string - UI mode (auto/smartmobile/desktop)
     *   - Any other custom claims
     * @return GeneratedJwt
     */
    public function generateAccessToken(string $userId, array $customClaims = []): GeneratedJwt
    {
        $claims = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'aud' => $customClaims['aud'] ?? $this->issuer,
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'access',
        ];

        // Add custom Horde-specific claims
        if (!empty($customClaims['apps'])) {
            $claims['horde']['apps'] = $customClaims['apps'];
        }
        if (!empty($customClaims['lang'])) {
            $claims['horde']['lang'] = $customClaims['lang'];
        }
        if (!empty($customClaims['mode'])) {
            $claims['horde']['mode'] = $customClaims['mode'];
        }

        // Add any other custom claims (but don't override standard ones)
        foreach ($customClaims as $key => $value) {
            if (!in_array($key, ['aud', 'apps', 'lang', 'mode', 'iss', 'sub', 'exp', 'iat', 'nbf', 'jti', 'type'])) {
                $claims[$key] = $value;
            }
        }

        return $this->generator->generate($claims, $this->secret, $this->accessTokenTtl);
    }

    /**
     * Generate a refresh token for a user
     *
     * Refresh tokens have longer lifetime and are used to obtain new access tokens.
     *
     * @param string $userId User identifier
     * @param string|null $accessTokenJti JTI of the associated access token
     * @return GeneratedJwt
     */
    public function generateRefreshToken(string $userId, ?string $accessTokenJti = null): GeneratedJwt
    {
        $claims = [
            'iss' => $this->issuer,
            'sub' => $userId,
            'aud' => $this->issuer,
            'jti' => bin2hex(random_bytes(16)),
            'type' => 'refresh',
        ];

        if ($accessTokenJti !== null) {
            $claims['access_jti'] = $accessTokenJti;
        }

        return $this->generator->generate($claims, $this->secret, $this->refreshTokenTtl);
    }

    /**
     * Verify and decode a JWT access token
     *
     * @param string $token JWT token to verify
     * @param array $options Verification options:
     *   - 'verify_aud' => string|null - Expected audience (default: issuer)
     *   - 'leeway' => int - Clock skew leeway in seconds (default: 60)
     * @return VerifiedJwt
     * @throws InvalidArgumentException If token is invalid
     */
    public function verifyAccessToken(string $token, array $options = []): VerifiedJwt
    {
        $verifyOptions = [
            'verify_exp' => true,
            'verify_nbf' => true,
            'verify_iss' => $this->issuer,
            'verify_aud' => $options['verify_aud'] ?? $this->issuer,
            'leeway' => $options['leeway'] ?? 60,
        ];

        $verified = $this->verifier->verifyHs256($token, $this->secret, $verifyOptions);

        // Ensure it's an access token
        if (($verified->getClaim('type') ?? '') !== 'access') {
            throw new InvalidArgumentException('Token is not an access token');
        }

        return $verified;
    }

    /**
     * Verify and decode a JWT refresh token
     *
     * @param string $token JWT token to verify
     * @param array $options Verification options (same as verifyAccessToken)
     * @return VerifiedJwt
     * @throws InvalidArgumentException If token is invalid
     */
    public function verifyRefreshToken(string $token, array $options = []): VerifiedJwt
    {
        $verifyOptions = [
            'verify_exp' => true,
            'verify_nbf' => true,
            'verify_iss' => $this->issuer,
            'verify_aud' => $options['verify_aud'] ?? $this->issuer,
            'leeway' => $options['leeway'] ?? 60,
        ];

        $verified = $this->verifier->verifyHs256($token, $this->secret, $verifyOptions);

        // Ensure it's a refresh token
        if (($verified->getClaim('type') ?? '') !== 'refresh') {
            throw new InvalidArgumentException('Token is not a refresh token');
        }

        return $verified;
    }

    /**
     * Refresh an access token using a refresh token
     *
     * @param string $refreshToken Valid refresh token
     * @param array $customClaims Additional claims for the new access token
     * @return GeneratedJwt New access token
     * @throws InvalidArgumentException If refresh token is invalid
     */
    public function refreshAccessToken(string $refreshToken, array $customClaims = []): GeneratedJwt
    {
        $verified = $this->verifyRefreshToken($refreshToken);

        $userId = $verified->getSubject();
        if ($userId === null) {
            throw new InvalidArgumentException('Refresh token missing subject claim');
        }

        return $this->generateAccessToken($userId, $customClaims);
    }

    /**
     * Extract JWT token from Authorization header
     *
     * Supports both "Bearer token" and "token" formats.
     *
     * @param string $authorizationHeader Value of Authorization header
     * @return string|null JWT token, or null if not found
     */
    public function extractTokenFromHeader(string $authorizationHeader): ?string
    {
        $parts = explode(' ', trim($authorizationHeader), 2);

        if (count($parts) === 2 && strtolower($parts[0]) === 'bearer') {
            return trim($parts[1]);
        }

        if (count($parts) === 1) {
            return trim($parts[0]);
        }

        return null;
    }

    /**
     * Get the configured issuer
     *
     * @return string
     */
    public function getIssuer(): string
    {
        return $this->issuer;
    }

    /**
     * Get the access token TTL
     *
     * @return int Seconds
     */
    public function getAccessTokenTtl(): int
    {
        return $this->accessTokenTtl;
    }

    /**
     * Get the refresh token TTL
     *
     * @return int Seconds
     */
    public function getRefreshTokenTtl(): int
    {
        return $this->refreshTokenTtl;
    }
}
