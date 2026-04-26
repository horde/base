<?php

declare(strict_types=1);

namespace Horde\Horde\Portal;

use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Config\RegistryState;
use Horde\Core\View\ResponsiveTemplateView;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Exception;
use Horde_Registry;

/**
 * Responsive Portal Controller
 *
 * Modern responsive portal/home page showing user's available applications.
 * Replaces the traditional portal view and smartmobile view with a unified
 * responsive design.
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
class ResponsivePortalController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;
    /**
     * Handle the portal request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Get registry from request attributes
        $registry = $request->getAttribute('registry');
        $injector = $GLOBALS['injector'] ?? null;

        // Check authentication
        if (!$registry->isAuthenticated()) {
            // User not authenticated, redirect to login
            $webroot = $registry->get('webroot', 'horde');
            return $this->redirectToLogin(
                $webroot . '/auth/login',
                $request->getUri()->getPath()
            );
        }

        // Get ResponsiveAssets helper
        $responsiveAssets = new ResponsiveAssets(new RegistryState($registry->applications));

        // Get user information
        $identity = $injector?->getInstance('Horde_Core_Factory_Identity')->create();
        $fullname = $identity?->getValue('fullname') ?? $registry->getAuth();

        // Get asset paths
        $themesUri = $registry->get('themesuri', 'horde');
        $webroot = $registry->get('webroot', 'horde');

        // Generate logout URL with CSRF token from session
        $session = $GLOBALS['session'] ?? null;
        $logoutToken = $session ? $session->getToken() : '';
        $logoutUrl = $webroot . '/login.php?logout_reason=logout&horde_logout_token=' . urlencode($logoutToken);

        // Get list of available applications
        $apps = $this->getApplicationList($registry);

        // Check for JWT bootstrap tokens from login
        $jwtBootstrap = $_SESSION['__horde']['jwt_bootstrap'] ?? null;
        $jwtBootstrapJson = $jwtBootstrap ? json_encode($jwtBootstrap, JSON_THROW_ON_ERROR) : 'null';
        // Clear the flash data after reading
        if ($jwtBootstrap) {
            unset($_SESSION['__horde']['jwt_bootstrap']);
        }

        // Build view data for template
        $viewData = [
            // Asset URLs from ResponsiveAssets helper
            'cssUrls' => $responsiveAssets->getCssUrls('horde'),
            'jsUrls' => $responsiveAssets->getJsUrls('horde', ['portal.js']),

            // Theme info
            'theme' => $responsiveAssets->getTheme(),

            // Registry paths (for logo, icons, etc.)
            'themesUri' => $themesUri,
            'webroot' => $webroot,

            // User info
            'fullname' => $fullname,
            'logoutUrl' => $logoutUrl,

            // Application list
            'apps' => $apps,

            // JWT bootstrap for JS (if present)
            'jwtBootstrapJson' => $jwtBootstrapJson,
        ];

        // Create view and render
        $templatePath = __DIR__ . '/../../templates/portal/responsive.html.php';
        $view = new ResponsiveTemplateView($templatePath, $viewData);

        // Return response
        $streamFactory = new StreamFactory();
        $response = new Response();
        $stream = $streamFactory->createStream($view->render());

        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(200);
    }

    /**
     * Get list of available applications
     *
     * @param Horde_Registry $registry
     * @return array Array of apps with name, icon, url
     */
    private function getApplicationList($registry): array
    {
        $apps = [];

        foreach (array_diff($registry->listApps(), ['horde']) as $app) {
            try {
                $icon = $registry->get('icon', $app);
                // Convert Horde_Themes_Image to string
                $iconUrl = is_object($icon) ? (string) $icon : $icon;

                $apps[] = [
                    'id' => $app,
                    'name' => $registry->get('name', $app),
                    'icon' => $iconUrl,
                    'url' => (string) $registry->getInitialPage($app),
                    'has_mobile' => $registry->hasView($registry::VIEW_SMARTMOBILE, $app),
                ];
            } catch (Exception $e) {
                // Skip apps that fail to load
                continue;
            }
        }

        // Sort by name
        usort($apps, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $apps;
    }
}
