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
 * Code outside horde/base should use the equivalent class
 * {@see \Horde\Core\Auth\Jwt\JwtServiceFactory} from horde/core.
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
        $secretFile = $jwtConfig['secret_file'] ?? '';
        $issuer = $jwtConfig['issuer'] ?? ($_SERVER['SERVER_NAME'] ?? 'horde.example.com');
        $accessTtl = $jwtConfig['access_ttl'] ?? 3600;
        $refreshTtl = $jwtConfig['refresh_ttl'] ?? 2592000;

        // Determine secret file path
        if (empty($secretFile)) {
            // Default location: var/config/horde/jwt.secret
            if (defined('HORDE_CONFIG_BASE')) {
                $secretFile = HORDE_CONFIG_BASE . '/horde/jwt.secret';
            } elseif (defined('HORDE_BASE')) {
                $secretFile = HORDE_BASE . '/../../../var/config/horde/jwt.secret';
            } else {
                throw new InvalidArgumentException(
                    'JWT is enabled but secret_file is not configured and HORDE_CONFIG_BASE/HORDE_BASE constants are not defined. '
                    . 'Set $conf[\'auth\'][\'jwt\'][\'secret_file\'] in conf.php.'
                );
            }
        } elseif (!str_starts_with($secretFile, '/')) {
            // Relative path - resolve relative to HORDE_CONFIG_BASE or HORDE_BASE
            if (defined('HORDE_CONFIG_BASE')) {
                $secretFile = HORDE_CONFIG_BASE . '/' . $secretFile;
            } elseif (defined('HORDE_BASE')) {
                $secretFile = HORDE_BASE . '/' . $secretFile;
            } else {
                throw new InvalidArgumentException(
                    'JWT secret_file is a relative path but HORDE_CONFIG_BASE/HORDE_BASE constants are not defined. '
                    . 'Use an absolute path or define HORDE_CONFIG_BASE.'
                );
            }
        }

        // Read secret from file
        if (!file_exists($secretFile)) {
            throw new InvalidArgumentException(
                "JWT is enabled but secret file does not exist: {$secretFile}. "
                . 'Generate a secret with: openssl rand -base64 32 > ' . $secretFile
            );
        }

        if (!is_readable($secretFile)) {
            throw new InvalidArgumentException(
                "JWT secret file exists but is not readable: {$secretFile}. "
                . 'Check file permissions (should be 600 and owned by web server user).'
            );
        }

        $secret = trim(file_get_contents($secretFile));

        // Validate secret
        if (empty($secret)) {
            throw new InvalidArgumentException(
                "JWT secret file is empty: {$secretFile}. "
                . 'Generate a secret with: openssl rand -base64 32 > ' . $secretFile
            );
        }

        if (strlen($secret) < 32) {
            throw new InvalidArgumentException(
                'JWT secret must be at least 256 bits (32 bytes). '
                . 'Generate a new secret with: openssl rand -base64 32 > ' . $secretFile
            );
        }

        return new JwtService($secret, $issuer, $accessTtl, $refreshTtl);
    }
}
