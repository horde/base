<?php

/**
 * JWT Authentication Service Bootstrap
 *
 * Registers JWT and Authentication services with the Horde dependency injector.
 * Include this file in your application bootstrap or horde/config/hooks.php.
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

use Horde\Horde\Factory\JwtServiceFactory;
use Horde\Horde\Factory\AuthenticationServiceFactory;
use Horde\Horde\Service\JwtService;
use Horde\Horde\Service\AuthenticationService;

// Register JWT Service factory
$GLOBALS['injector']->bindFactory(
    JwtService::class,
    function ($injector) {
        $factory = new JwtServiceFactory();
        return $factory->create($injector);
    },
    'singleton'
);

// Register Authentication Service factory
$GLOBALS['injector']->bindFactory(
    AuthenticationService::class,
    function ($injector) {
        $factory = new AuthenticationServiceFactory();
        return $factory->create($injector);
    },
    'singleton'
);

// Also register with string names for backwards compatibility
$GLOBALS['injector']->bindFactory(
    'Horde_Jwt_Service',
    function ($injector) {
        return $injector->getInstance(JwtService::class);
    },
    'singleton'
);

$GLOBALS['injector']->bindFactory(
    'Horde_Authentication_Service',
    function ($injector) {
        return $injector->getInstance(AuthenticationService::class);
    },
    'singleton'
);
