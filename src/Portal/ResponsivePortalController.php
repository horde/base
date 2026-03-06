<?php

declare(strict_types=1);

namespace Horde\Horde\Portal;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;
use Horde\Http\StreamFactory;

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
            $response = new Response();
            return $response
                ->withStatus(302)
                ->withHeader('Location', $webroot . '/auth/login?url=' . urlencode($request->getUri()->getPath()));
        }

        // Get user information
        $identity = $injector?->getInstance('Horde_Core_Factory_Identity')->create();
        $fullname = $identity?->getValue('fullname') ?? $registry->getAuth();

        // Get asset paths
        $themesUri = $registry->get('themesuri', 'horde');
        $webroot = $registry->get('webroot', 'horde');
        $jsUri = $registry->get('jsuri', 'horde');

        // Use clean responsive logout endpoint (no token needed)
        $logoutUrl = $webroot . '/auth/logout';

        // Get list of available applications
        $apps = $this->getApplicationList($registry);

        // Render the portal
        $html = $this->renderPortal($fullname, $apps, $themesUri, $webroot, $jsUri, $logoutUrl);

        // Return response
        $streamFactory = new StreamFactory();
        $response = new Response();
        $stream = $streamFactory->createStream($html);
        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(200);
    }

    /**
     * Get list of available applications
     *
     * @param \Horde_Registry $registry
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
            } catch (\Exception $e) {
                // Skip apps that fail to load
                continue;
            }
        }

        // Sort by name
        usort($apps, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $apps;
    }

    /**
     * Render the portal HTML
     *
     * @param string $fullname User's full name
     * @param array $apps List of applications
     * @param string $themesUri Theme URI
     * @param string $webroot Webroot path
     * @param string $jsUri JavaScript URI
     * @param string $logoutUrl Logout URL
     * @return string HTML
     */
    private function renderPortal(string $fullname, array $apps, string $themesUri, string $webroot, string $jsUri, string $logoutUrl): string
    {
        // Check for JWT bootstrap tokens from login
        $jwtBootstrap = $_SESSION['__horde']['jwt_bootstrap'] ?? null;
        $jwtBootstrapJson = $jwtBootstrap ? json_encode($jwtBootstrap, JSON_THROW_ON_ERROR) : 'null';
        // Clear the flash data after reading
        if ($jwtBootstrap) {
            unset($_SESSION['__horde']['jwt_bootstrap']);
        }

        // Render template
        ob_start();
        require __DIR__ . '/../../templates/portal/responsive.html.php';
        return ob_get_clean();
    }

    /**
     * Escape HTML entities
     *
     * @param string $text
     * @return string
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
