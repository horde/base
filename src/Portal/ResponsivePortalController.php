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

        // Get user information
        $identity = $injector?->getInstance('Horde_Core_Factory_Identity')->create();
        $fullname = $identity?->getValue('fullname') ?? $registry->getAuth();

        // Get asset paths
        $themesUri = $registry->get('themesuri', 'horde');
        $webroot = $registry->get('webroot', 'horde');

        // Generate logout token for secure logout
        $logoutToken = $injector?->getInstance('Horde_Token');
        $logoutUrl = $webroot . '/login.php?logout_reason=logout';
        if ($logoutToken) {
            try {
                $logoutUrl .= '&logout_token=' . $logoutToken->get('horde.logout');
            } catch (\Exception $e) {
                // If token generation fails, use URL without token
            }
        }

        // Get list of available applications
        $apps = $this->getApplicationList($registry);

        // Render the portal
        $html = $this->renderPortal($fullname, $apps, $themesUri, $webroot, $logoutUrl);

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
     * @param string $logoutUrl Logout URL with token
     * @return string HTML
     */
    private function renderPortal(string $fullname, array $apps, string $themesUri, string $webroot, string $logoutUrl): string
    {
        $appsHtml = '';
        foreach ($apps as $app) {
            $appName = $this->escapeHtml($app['name']);
            $appUrl = $this->escapeHtml($app['url']);
            $appIcon = $this->escapeHtml($app['icon']);

            $appsHtml .= <<<HTML
            <a href="{$appUrl}" class="app-card">
                <div class="app-icon">
                    <img src="{$appIcon}" alt="{$appName}">
                </div>
                <div class="app-name">{$appName}</div>
            </a>

HTML;
        }

        $escapedFullname = $this->escapeHtml($fullname);

        return <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal - Horde</title>
    <link rel="stylesheet" href="{$themesUri}/default/responsive.css">
</head>
<body class="portal-page">
    <header class="portal-header">
        <div class="container">
            <div class="portal-brand">
                <img src="{$themesUri}/default/graphics/logo.png" alt="Horde" class="portal-logo">
            </div>
            <div class="portal-user">
                <span class="user-name">{$escapedFullname}</span>
                <a href="{$this->escapeHtml($logoutUrl)}" class="btn btn-secondary btn-sm">Logout</a>
            </div>
        </div>
    </header>

    <main class="portal-main">
        <div class="container">
            <h1 class="portal-title">Your Applications</h1>

            <div class="app-grid">
                {$appsHtml}
            </div>
        </div>
    </main>

    <footer class="portal-footer">
        <div class="container">
            <p>&copy; 2026 <a href="https://www.horde.org/">The Horde Project</a></p>
        </div>
    </footer>
</body>
</html>
HTML;
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
