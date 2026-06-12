<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Auth\Jwt\JwtService;
use Horde\Horde\Service\AuthenticationService;
use Horde\Injector\Injector;
use Horde_Registry;
use Psr\Log\LoggerInterface;
use Exception;

/**
 * Factory for Authentication Service
 *
 * Creates AuthenticationService instance with optional JWT support.
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
class AuthenticationServiceFactory
{
    /**
     * Create AuthenticationService instance
     *
     * @param Injector $injector
     * @return AuthenticationService
     */
    public function create(Injector $injector): AuthenticationService
    {
        $registry = $injector->getInstance('Horde_Registry');
        $logger = $injector->getInstance(LoggerInterface::class);
        $credentialStore = $injector->getInstance(AuthCredentialStore::class);

        // Try to get JWT service. Horde\Core\Auth\Jwt\JwtService carries
        // a #[Factory] attribute pointing at JwtServiceFactory; that
        // factory returns null when JWT is not configured. The injector
        // wraps the null in a NotFoundException, which we catch and
        // proceed without JWT (AuthenticationService falls back to
        // session-only mode).
        $jwtService = null;
        try {
            $jwtService = $injector->getInstance(JwtService::class);
        } catch (Exception $e) {
            // JWT not configured or misconfigured - proceed without it.
        }

        return new AuthenticationService($registry, $logger, $credentialStore, $jwtService);
    }
}
