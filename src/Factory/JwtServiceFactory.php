<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Horde\Service\JwtService;
use Horde\Injector\Injector;
use InvalidArgumentException;

/**
 * Factory for JWT Service
 *
 * Creates JwtService instance with configuration from Horde config.
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
class JwtServiceFactory
{
    /**
     * Create JwtService instance
     *
     * @param Injector $injector
     * @return JwtService|null Returns null if JWT is not configured
     * @throws InvalidArgumentException If JWT is enabled but misconfigured
     */
    public function create(Injector $injector): ?JwtService
    {
        $conf = $GLOBALS['conf'] ?? [];
        $jwtConfig = $conf['auth']['jwt'] ?? [];

        // Check if JWT is enabled
        $enabled = $jwtConfig['enabled'] ?? false;
        if (!$enabled) {
            return null;
        }

        // Get configuration values
        $secret = $jwtConfig['secret'] ?? '';
        $issuer = $jwtConfig['issuer'] ?? ($_SERVER['SERVER_NAME'] ?? 'horde.example.com');
        $accessTtl = $jwtConfig['access_ttl'] ?? 3600;
        $refreshTtl = $jwtConfig['refresh_ttl'] ?? 2592000;

        // Validate secret
        if (empty($secret)) {
            throw new InvalidArgumentException(
                'JWT is enabled but no secret is configured. ' .
                'Run bin/horde-jwt-setup or set JWT_SECRET environment variable.'
            );
        }

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException(
                'JWT secret must be at least 256 bits (32 bytes). ' .
                'Run bin/horde-jwt-setup to generate a secure secret.'
            );
        }

        return new JwtService($secret, $issuer, $accessTtl, $refreshTtl);
    }
}
