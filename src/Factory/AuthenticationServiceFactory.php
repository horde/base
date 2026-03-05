<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\JwtService;
use Horde\Injector\Injector;
use Horde_Registry;

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

        // Try to get JWT service (may be null if not configured)
        $jwtService = null;
        try {
            $jwtServiceFactory = new JwtServiceFactory();
            $jwtService = $jwtServiceFactory->create($injector);
        } catch (\Exception $e) {
            // JWT not configured or misconfigured - proceed without it
            // AuthenticationService will fall back to session-only mode
        }

        return new AuthenticationService($registry, $jwtService);
    }
}
