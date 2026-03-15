<?php

declare(strict_types=1);

namespace Horde\Horde\Admin\Traits;

use Horde\Core\Config\State;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Trait for admin_secret authentication with security validation
 *
 * Provides authentication via admin_secret Bearer token for admin API endpoints.
 * Enforces minimum secret length to prevent weak secrets.
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
trait AdminAuthenticationTrait
{
    /**
     * Configuration state
     *
     * @var State
     */
    private State $config;

    /**
     * Minimum secret length (80% of standard 64-char hex secret)
     *
     * Standard secret generation:
     * - bin2hex(random_bytes(32)) = 64 chars (hex encoding)
     * - base64_encode(random_bytes(32)) = 44 chars (base64 encoding)
     * - hordectl generates 128 chars: base64_encode(random_bytes(96))
     *
     * Minimum 51 chars allows 20% variance while rejecting weak secrets.
     * All properly generated secrets exceed this minimum.
     *
     * @var int
     */
    private const MIN_SECRET_LENGTH = 51;

    /**
     * Standard secret length (32 bytes as hex)
     *
     * Note: hordectl actually generates 128-character base64 secrets.
     * This constant represents the minimum acceptable standard.
     *
     * @var int
     */
    private const STANDARD_SECRET_LENGTH = 64;

    /**
     * Authenticate request with admin_secret
     *
     * Validates:
     * 1. Admin API is enabled
     * 2. Secret is configured and meets minimum length
     * 3. Authorization header is present and valid
     * 4. Provided secret matches configured secret (timing-safe)
     *
     * @param ServerRequestInterface $request
     * @return bool True if authenticated
     */
    protected function authenticate(ServerRequestInterface $request): bool
    {
        // Check if admin API is enabled
        if (!$this->config->get('admin_api.enabled', false)) {
            return false;
        }

        // Get admin_secret from config
        $adminSecret = $this->config->get('admin_api.admin_secret', '');

        // Ensure it's a string (config might return null)
        if (!is_string($adminSecret)) {
            $adminSecret = '';
        }

        // Validate secret strength
        if (!$this->isValidSecret($adminSecret)) {
            // Log security warning if secret is too weak
            error_log(
                'SECURITY WARNING: admin_secret is empty or too short. '
                . 'Minimum length: ' . self::MIN_SECRET_LENGTH . ' characters. '
                . 'Use hordectl to generate a secure secret.'
            );
            return false;
        }

        // Get Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        if (empty($authHeader)) {
            return false;
        }

        // Extract Bearer token
        if (!preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            return false;
        }

        $providedSecret = $matches[1];

        // Timing-safe comparison
        return hash_equals($adminSecret, $providedSecret);
    }

    /**
     * Validate that admin_secret meets minimum security requirements
     *
     * Requirements:
     * - Not null or empty
     * - At least 51 characters (80% of standard 64-char hex secret)
     *
     * This prevents:
     * - Null/unset secrets (no authentication)
     * - Empty secrets (no authentication)
     * - Very short secrets (trivially brute-forceable)
     * - Weak secrets like "password" or "123456"
     *
     * @param string|null $secret The admin_secret to validate
     * @return bool True if secret is strong enough
     */
    private function isValidSecret(?string $secret): bool
    {
        // Null check (handles unset config keys)
        if ($secret === null) {
            return false;
        }

        // Empty check
        if ($secret === '') {
            return false;
        }

        // Length check (minimum 80% of standard secret length)
        if (strlen($secret) < self::MIN_SECRET_LENGTH) {
            return false;
        }

        return true;
    }

    /**
     * Get minimum required secret length
     *
     * Useful for error messages and documentation.
     *
     * @return int Minimum secret length in characters
     */
    protected function getMinSecretLength(): int
    {
        return self::MIN_SECRET_LENGTH;
    }

    /**
     * Get standard secret length
     *
     * Useful for secret generation and documentation.
     *
     * @return int Standard secret length in characters
     */
    protected function getStandardSecretLength(): int
    {
        return self::STANDARD_SECRET_LENGTH;
    }

    /**
     * Generate a cryptographically secure admin secret
     *
     * Generates 32 bytes of randomness as hexadecimal string (64 chars).
     * This method is provided as a reference implementation.
     *
     * @return string 64-character hexadecimal secret
     * @throws \Exception If random_bytes() fails
     */
    protected function generateSecret(): string
    {
        return bin2hex(random_bytes(32));
    }
}
