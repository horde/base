<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\Factory;

use Exception;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Horde\Login;
use Horde\Horde\Service\AuditService;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\LoginService;
use Horde\Horde\Service\RedirectValidationService;
use Horde\Injector\Injector;
use Psr\Log\LoggerInterface;

/**
 * Factory for LoginService.
 */
class LoginServiceFactory
{
    public function create(Injector $injector): LoginService
    {
        $registry = $injector->getInstance('Horde_Registry');
        $logger = $injector->getInstance(LoggerInterface::class);
        $loginFormBuilder = $injector->getInstance(Login::class);
        $redirectValidator = $injector->getInstance(RedirectValidationService::class);
        $auditService = $injector->getInstance(AuditService::class);

        // AuthenticationService may fail if JWT is not configured
        try {
            $authService = $injector->getInstance(AuthenticationService::class);
        } catch (Exception $e) {
            $authServiceFactory = new AuthenticationServiceFactory();
            $authService = $authServiceFactory->create($injector);
        }

        $providerConfig = $injector->getInstance(OAuthProviderConfigRepository::class);

        return new LoginService(
            $registry,
            $logger,
            $loginFormBuilder,
            $redirectValidator,
            $auditService,
            $authService,
            $providerConfig,
            $GLOBALS['conf'] ?? [],
        );
    }
}
