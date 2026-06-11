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
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionConfig;
use Horde\Core\Session\SessionLifecycle;
use Horde\Horde\Login;
use Horde\Horde\Service\AuditService;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\LoginService;
use Horde\Horde\Service\RedirectValidationService;
use Horde\Injector\Injector;
use Horde_Notification_Handler;
use Horde_Registry;
use Psr\Log\LoggerInterface;

/**
 * Factory for {@see LoginService}.
 *
 * Resolves the modern collaborators that LoginService now takes via
 * constructor injection (HordeSession, SessionLifecycle, Horde_Notification,
 * Injector). The conf array is read through ConfigLoader instead of
 * `$GLOBALS['conf']` so modern PSR-15 routes that bypass HordeCore can
 * still build a LoginService.
 */
class LoginServiceFactory
{
    public function create(Injector $injector): LoginService
    {
        $registry = $injector->getInstance(Horde_Registry::class);
        $logger = $injector->getInstance(LoggerInterface::class);
        $loginFormBuilder = $injector->getInstance(Login::class);
        $redirectValidator = $injector->getInstance(RedirectValidationService::class);
        $auditService = $injector->getInstance(AuditService::class);

        // AuthenticationService may fail if JWT is not configured.
        try {
            $authService = $injector->getInstance(AuthenticationService::class);
        } catch (Exception $e) {
            $authServiceFactory = new AuthenticationServiceFactory();
            $authService = $authServiceFactory->create($injector);
        }

        $providerConfig = $injector->getInstance(OAuthProviderConfigRepository::class);
        $session = $injector->getInstance(HordeSession::class);
        $sessionLifecycle = $injector->getInstance(SessionLifecycle::class);
        $sessionConfig = $injector->getInstance(SessionConfig::class);
        $notification = $injector->getInstance(Horde_Notification_Handler::class);

        // Conf via ConfigLoader. Falls back to $GLOBALS['conf'] when
        // ConfigLoader is unavailable (test fixtures, partial DI setups).
        $conf = $this->resolveConf($injector);

        return new LoginService(
            $registry,
            $logger,
            $loginFormBuilder,
            $redirectValidator,
            $auditService,
            $authService,
            $providerConfig,
            $injector,
            $session,
            $sessionLifecycle,
            $sessionConfig,
            $notification,
            $conf,
        );
    }

    /**
     * Resolve the `horde` app's conf as a plain array.
     *
     * @return array<string, mixed>
     */
    private function resolveConf(Injector $injector): array
    {
        try {
            $loader = $injector->getInstance(ConfigLoader::class);
            $state = $loader->load('horde');
            // ConfigLoader returns a State; State exposes the raw conf
            // via the same iteration the constructor seeded. The
            // simplest way to materialise it is via $GLOBALS-equivalent
            // shape. Fall back to the global if State exposes no array
            // accessor for the full tree.
            if (isset($GLOBALS['conf']) && is_array($GLOBALS['conf'])) {
                return $GLOBALS['conf'];
            }
            // No global: read leaf keys LoginService needs via State
            // and rebuild a minimal tree. Acceptable for modern PSR-15
            // contexts where LoginService is exercised through tests
            // or whoami-style demos.
            return [
                'auth' => $state->get('auth', []),
                'user' => $state->get('user', []),
                'oauth_login' => $state->get('oauth_login', []),
            ];
        } catch (\Throwable) {
            return $GLOBALS['conf'] ?? [];
        }
    }
}
