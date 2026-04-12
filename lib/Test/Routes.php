<?php

use Horde\Http\Uri;
use Horde\Routes\Mapper;
use Horde\Routes\Printer;

/**
 * Routes display helper for Horde test page.
 *
 * Displays all registered routes for the application with their
 * configuration details.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
class Horde_Test_Routes
{
    /**
     * Generate HTML output for routes display page.
     *
     * @param Horde_Registry $registry  The registry instance
     * @param Horde_Injector $injector  The injector instance
     *
     * @return string  HTML output
     */
    public static function render($registry, $injector)
    {
        $app = $registry->getApp();
        $webroot = $registry->get('webroot', $app);

        // Webroot may be a full URL (https://host/path) or just a path (/path).
        // Normalise to a path-only $webrootPath and a full $baseUrl.
        $parsed = new Uri($webroot);
        if ($parsed->getHost() !== '') {
            $baseUrl = $parsed->getScheme() . '://' . $parsed->getAuthority();
            $webrootPath = $parsed->getPath();
        } else {
            $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
            $host = $_SERVER['HTTP_HOST'] ?? 'example.com';
            $baseUrl = $scheme . '://' . $host;
            $webrootPath = $webroot;
        }

        ob_start();
        ?>
<h1>Registered Routes for <?php echo htmlspecialchars(ucfirst($app)) ?></h1>

<div style="background-color: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; margin: 15px 0;">
    <strong>About Routes:</strong>
    <p style="margin: 10px 0 0 0;">
        Routes define URL patterns and map them to controllers. This page shows all routes
        registered for the <strong><?php echo htmlspecialchars($app) ?></strong> application.
    </p>
    <p style="margin: 10px 0 0 0;">
        <strong>Base URL:</strong> <code><?php echo htmlspecialchars($baseUrl . $webrootPath) ?></code>
    </p>
</div>

<?php
        // Load routes for the app
        $mapper = new Mapper();
        $fileroot = $registry->get('fileroot', $app);
        $routeFile = $fileroot . '/config/routes.php';

        if (!file_exists($routeFile)) {
            ?>
<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
    <strong style="color:#856404">⚠ INFO</strong>
    No routes file found at <code><?php echo htmlspecialchars($routeFile) ?></code>
    <br /><br />
    This application does not use the routes system.
</div>
<?php
            return ob_get_clean();
        }

        // Set up mapper prefix
        $mapper->prefix = $webrootPath;

        // Load the routes
        try {
            include $routeFile;

            // Also load local routes if they exist
            if (file_exists($fileroot . '/config/routes.local.php')) {
                include $fileroot . '/config/routes.local.php';
            }
        } catch (Exception $e) {
            ?>
<div style="background-color: #f8d7da; border-left: 4px solid #e74c3c; padding: 15px; margin: 15px 0;">
    <strong style="color:#e74c3c">✗ ERROR</strong>
    Failed to load routes: <?php echo htmlspecialchars($e->getMessage()) ?>
</div>
<?php
            return ob_get_clean();
        }

        // Get route information using Printer
        $printer = new Printer($mapper);
        $routes = $printer->getRoutes();

        if (empty($routes)) {
            ?>
<div style="background-color: #fff3cd; border-left: 4px solid #ffc107; padding: 15px; margin: 15px 0;">
    <strong style="color:#856404">⚠ INFO</strong>
    No routes registered for this application.
</div>
<?php
            return ob_get_clean();
        }

        // Group routes by name for display, excluding secondary routes
        // (secondary routes are included in the secondaryPaths field of their primary route)
        $routesByName = [];
        foreach ($routes as $route) {
            // Skip secondary routes - they're shown with their primary route
            if ($route['secondary']) {
                continue;
            }

            $name = $route['name'] ?: '(anonymous)';
            if (!isset($routesByName[$name])) {
                $routesByName[$name] = [];
            }
            $routesByName[$name][] = $route;
        }

        ?>
<div style="margin: 20px 0;">
    <p><strong>Total Routes:</strong> <?php echo count($routesByName) ?> (<?php echo count($routes) ?> including method variants)</p>
</div>

<div style="display: flex; flex-direction: column; gap: 15px;">
<?php
        foreach ($routesByName as $name => $routeVariants) {
            $firstRoute = $routeVariants[0];
            $path = $firstRoute['path'];
            // Path from Printer includes prefix and is normalized
            $fullUrl = $baseUrl . $path;

            // Extract controller from hardcodes
            $controller = '';
            if (preg_match('/:controller=>"([^"]+)"/', $firstRoute['hardcodes'], $matches)) {
                $controller = $matches[1];
            }

            // Collect all methods
            $methods = [];
            foreach ($routeVariants as $variant) {
                if (!empty($variant['method'])) {
                    $methods[] = $variant['method'];
                }
            }
            $methodsStr = empty($methods) ? 'ANY' : implode(', ', $methods);

            // Get secondary paths if any
            $secondaryPaths = $firstRoute['secondaryPaths'] ?? [];

            ?>
    <div style="background: white; border-radius: 6px; box-shadow: 0 1px 3px rgba(0, 0, 0, 0.1); padding: 15px;">
        <div style="margin-bottom: 10px;">
            <strong style="color: #2c3e50; font-size: 16px;"><?php echo htmlspecialchars($name) ?></strong>
        </div>
        <ul style="list-style: none; padding: 0; margin: 0; font-size: 14px; line-height: 1.8;">
            <li><strong>Path:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($path) ?></code></li>
            <li><strong>Full URL:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($fullUrl) ?></code></li>
            <?php if (!empty($secondaryPaths)): ?>
            <li><strong>Secondary Paths:</strong>
                <?php foreach ($secondaryPaths as $sp): ?>
                <code style="background: #fff3cd; padding: 2px 6px; border-radius: 3px; margin-right: 5px;"><?php echo htmlspecialchars($sp) ?></code>
                <?php endforeach; ?>
            </li>
            <?php endif; ?>
            <li><strong>Methods:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($methodsStr) ?></code></li>
            <?php if ($controller): ?>
            <li><strong>Controller:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px;"><?php echo htmlspecialchars($controller) ?></code></li>
            <?php endif; ?>
            <?php if (!empty($firstRoute['hardcodes'])): ?>
            <li><strong>Defaults:</strong> <code style="background: #f8f9fa; padding: 2px 6px; border-radius: 3px; font-size: 12px;"><?php echo htmlspecialchars($firstRoute['hardcodes']) ?></code></li>
            <?php endif; ?>
        </ul>
    </div>
<?php
        }
        ?>
</div>
<?php
        return ob_get_clean();
    }
}
