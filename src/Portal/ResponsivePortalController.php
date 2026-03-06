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

        // Use clean responsive logout endpoint (no token needed)
        $logoutUrl = $webroot . '/auth/logout';

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
        $escapedLogoutUrl = $this->escapeHtml($logoutUrl);
        $escapedWebroot = $this->escapeHtml($webroot);

        // Check for JWT bootstrap tokens from login
        $jwtBootstrap = $_SESSION['__horde']['jwt_bootstrap'] ?? null;
        $jwtBootstrapJson = $jwtBootstrap ? json_encode($jwtBootstrap, JSON_THROW_ON_ERROR) : 'null';
        // Clear the flash data after reading
        if ($jwtBootstrap) {
            unset($_SESSION['__horde']['jwt_bootstrap']);
        }

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
                <button id="logout-btn" class="btn btn-secondary btn-sm">Logout</button>
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

    <script>
    // JWT Token Management with Rate Limiting
    (function() {
        const WEBROOT = '{$escapedWebroot}';
        const LOGOUT_URL = '{$escapedLogoutUrl}';
        const JWT_BOOTSTRAP = {$jwtBootstrapJson};

        // Configuration
        const REFRESH_COOLDOWN = 30000; // 30 seconds between refresh attempts
        const REFRESH_BUFFER = 300000;  // Refresh when token expires in < 5 minutes
        const LAST_REFRESH_KEY = 'horde_last_token_refresh';
        const REFRESH_IN_PROGRESS_KEY = 'horde_refresh_in_progress';

        /**
         * Refresh access token with rate limiting
         *
         * Prevents multiple tabs from refreshing simultaneously by:
         * 1. Checking last refresh timestamp (30-second cooldown)
         * 2. Setting in-progress flag for cross-tab coordination
         * 3. Waiting for other tab's refresh if one is in progress
         */
        async function refreshAccessToken() {
            // Check if refresh is already in progress (another tab)
            const refreshInProgress = localStorage.getItem(REFRESH_IN_PROGRESS_KEY);
            if (refreshInProgress) {
                const inProgressTime = parseInt(refreshInProgress);
                const elapsed = Date.now() - inProgressTime;

                // If refresh started less than 10 seconds ago, wait for it
                if (elapsed < 10000) {
                    console.log('Token refresh in progress in another tab, waiting...');
                    await new Promise(resolve => setTimeout(resolve, 2000));
                    // Assume other tab completed, reload tokens
                    return {
                        access_token: localStorage.getItem('access_token'),
                        expires_at: localStorage.getItem('token_expires_at')
                    };
                } else {
                    // Stale in-progress flag (other tab crashed?), clear it
                    localStorage.removeItem(REFRESH_IN_PROGRESS_KEY);
                }
            }

            // Check cooldown to prevent rapid refresh attempts
            const lastRefresh = localStorage.getItem(LAST_REFRESH_KEY);
            if (lastRefresh) {
                const timeSince = Date.now() - parseInt(lastRefresh);
                if (timeSince < REFRESH_COOLDOWN) {
                    console.log('Token refresh attempted too soon (' + Math.floor(timeSince/1000) + 's ago), skipping');
                    return {
                        access_token: localStorage.getItem('access_token'),
                        expires_at: localStorage.getItem('token_expires_at')
                    };
                }
            }

            // Set in-progress flag BEFORE making request
            localStorage.setItem(REFRESH_IN_PROGRESS_KEY, Date.now().toString());
            localStorage.setItem(LAST_REFRESH_KEY, Date.now().toString());

            try {
                const refreshToken = localStorage.getItem('refresh_token');

                const response = await fetch(WEBROOT + '/api/v1/auth/refresh', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({ refresh_token: refreshToken }),
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    throw new Error('Refresh failed: ' + response.status);
                }

                const data = await response.json();

                // Update localStorage with new tokens
                localStorage.setItem('access_token', data.access_token);
                localStorage.setItem('token_expires_at', data.expires_at * 1000);

                console.log('Token refreshed successfully');

                return data;

            } catch (error) {
                console.error('Error refreshing token:', error);
                // Clear rate limit on error so retry is possible
                localStorage.removeItem(LAST_REFRESH_KEY);
                throw error;
            } finally {
                // Clear in-progress flag
                localStorage.removeItem(REFRESH_IN_PROGRESS_KEY);
            }
        }

        /**
         * Bootstrap JWT tokens on page load
         */
        async function bootstrapJWT() {
            // Check if we have bootstrap tokens from login
            if (JWT_BOOTSTRAP && JWT_BOOTSTRAP.access_token && JWT_BOOTSTRAP.refresh_token) {
                console.log('Storing JWT tokens from login...');
                localStorage.setItem('access_token', JWT_BOOTSTRAP.access_token);
                localStorage.setItem('refresh_token', JWT_BOOTSTRAP.refresh_token);
                localStorage.setItem('token_expires_at', JWT_BOOTSTRAP.expires_at * 1000);
                console.log('JWT tokens stored successfully');
                return;
            }

            // Check if we already have tokens
            const accessToken = localStorage.getItem('access_token');
            const refreshToken = localStorage.getItem('refresh_token');

            if (accessToken && refreshToken) {
                console.log('JWT tokens already present');

                // Check if token needs refresh
                const expiresAt = parseInt(localStorage.getItem('token_expires_at'));
                const timeUntilExpiry = expiresAt - Date.now();

                if (timeUntilExpiry < REFRESH_BUFFER) {
                    console.log('Token expires soon, refreshing...');
                    try {
                        await refreshAccessToken();
                    } catch (error) {
                        console.error('Failed to refresh token:', error);
                    }
                }

                return;
            }

            // If no tokens and no bootstrap, try to get them from session
            console.log('No JWT tokens found, checking session...');

            try {
                // Call refresh endpoint without refresh_token to bootstrap
                const response = await fetch(WEBROOT + '/api/v1/auth/refresh', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json'},
                    body: JSON.stringify({}),
                    credentials: 'same-origin'
                });

                if (!response.ok) {
                    if (response.status === 401) {
                        console.log('No active session with JWT support');
                    } else {
                        console.error('Failed to bootstrap JWT:', response.status);
                    }
                    return;
                }

                const data = await response.json();

                // Store tokens
                localStorage.setItem('access_token', data.access_token);
                localStorage.setItem('refresh_token', data.refresh_token);
                localStorage.setItem('token_expires_at', Date.now() + (data.expires_in * 1000));

                console.log('JWT tokens bootstrapped from session');

            } catch (error) {
                console.error('Error bootstrapping JWT:', error);
            }
        }

        /**
         * Make API call with automatic token refresh
         *
         * Usage:
         *   makeApiCall('/horde/api/v1/some/endpoint', { method: 'POST', body: {...} })
         */
        async function makeApiCall(endpoint, options = {}) {
            // Check if token needs refresh
            const expiresAt = parseInt(localStorage.getItem('token_expires_at'));
            const timeUntilExpiry = expiresAt - Date.now();

            if (timeUntilExpiry < REFRESH_BUFFER) {
                try {
                    await refreshAccessToken();
                } catch (error) {
                    console.error('Failed to refresh token before API call:', error);
                    // Continue anyway - let API return 401 if token is invalid
                }
            }

            // Make API call with current token
            const accessToken = localStorage.getItem('access_token');
            return fetch(endpoint, {
                ...options,
                headers: {
                    ...options.headers,
                    'Authorization': 'Bearer ' + accessToken
                }
            });
        }

        /**
         * Handle logout
         */
        async function logout() {
            // Clear localStorage
            localStorage.removeItem('access_token');
            localStorage.removeItem('refresh_token');
            localStorage.removeItem('token_expires_at');
            localStorage.removeItem(LAST_REFRESH_KEY);
            localStorage.removeItem(REFRESH_IN_PROGRESS_KEY);

            // Call logout endpoint to destroy session
            try {
                await fetch(LOGOUT_URL, {
                    method: 'GET',
                    credentials: 'same-origin'
                });
            } catch (error) {
                console.error('Logout error:', error);
            }

            // Redirect to login
            window.location.href = WEBROOT + '/auth/login?logout=1';
        }

        // Set up logout button
        document.getElementById('logout-btn').addEventListener('click', function(e) {
            e.preventDefault();
            logout();
        });

        // Bootstrap on page load
        bootstrapJWT();

        // Expose API for other scripts
        window.HordeAuth = {
            refreshAccessToken: refreshAccessToken,
            makeApiCall: makeApiCall,
            logout: logout
        };
    })();
    </script>
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
