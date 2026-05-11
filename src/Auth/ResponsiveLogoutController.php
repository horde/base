<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Horde_Exception;
use Horde\Horde\Service\LoginService;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Horde\ValueObject\LogoutRequest;
use Horde_Auth;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Responsive Logout Controller
 *
 * Feature-complete logout endpoint: CSRF verification, audit logging,
 * session teardown, and redirect_on_logout support.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */
class ResponsiveLogoutController implements RequestHandlerInterface
{
    use RedirectResponseTrait;

    public function __construct(
        private readonly LoginService $loginService,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $registry = $request->getAttribute('registry');
        $webroot = $registry->get('webroot', 'horde');
        $queryParams = $request->getQueryParams();
        $serverParams = $request->getServerParams();

        $logoutRequest = new LogoutRequest(
            csrfToken: is_string($queryParams['horde_logout_token'] ?? null)
                ? $queryParams['horde_logout_token'] : null,
            reason: Horde_Auth::REASON_LOGOUT,
            message: is_string($queryParams['logout_msg'] ?? null)
                ? $queryParams['logout_msg'] : null,
            redirectUrl: is_string($queryParams['redirect'] ?? null)
                ? $queryParams['redirect'] : null,
            anchorString: is_string($queryParams['anchor_string'] ?? null)
                ? $queryParams['anchor_string'] : null,
            remoteAddr: (string) ($serverParams['REMOTE_ADDR'] ?? ''),
            forwardedFor: !empty($serverParams['HTTP_X_FORWARDED_FOR'])
                ? (string) $serverParams['HTTP_X_FORWARDED_FOR'] : null,
        );

        try {
            $result = $this->loginService->performLogout($logoutRequest);
        } catch (Horde_Exception $e) {
            // CSRF token invalid — redirect to index
            return $this->redirect($webroot . '/index.php');
        }

        return $this->redirect($result['redirect']);
    }
}
