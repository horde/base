<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Horde\Horde\Traits\RedirectResponseTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;

/**
 * Responsive Logout Controller
 *
 * Clean logout endpoint for responsive UI. Destroys session and redirects
 * to login page.
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
class ResponsiveLogoutController implements RequestHandlerInterface
{
    use RedirectResponseTrait;
    /**
     * Handle the logout request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Get registry from request attributes
        $registry = $request->getAttribute('registry');
        $injector = $GLOBALS['injector'] ?? null;
        $webroot = $registry->get('webroot', 'horde');

        // Clear authentication
        if ($registry && $registry->getAuth()) {
            $registry->clearAuth();
        }

        // Get redirect URL - either from query param or default to login
        $queryParams = $request->getQueryParams();
        $redirectUrl = $queryParams['redirect'] ?? ($webroot . '/auth/login?error=logout');

        // Create redirect response
        return $this->redirect($redirectUrl);
    }
}
