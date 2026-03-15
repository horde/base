<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */

namespace Horde\Horde\Admin;

use Horde\Core\Config\ConfigLoader;
use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Horde\Service\Health\HealthCheckService;
use Horde\Horde\Traits\JsonResponseTrait;
use Horde\Injector\Injector;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Health check REST API controller
 *
 * Provides diagnostic endpoints for checking Horde subsystems.
 * Exposes test.php functionality via REST API for admin use.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class HealthCheckController implements RequestHandlerInterface
{
    use AdminAuthenticationTrait;
    use JsonResponseTrait;

    private HealthCheckService $healthCheck;

    public function __construct(
        ConfigLoader $configLoader,
        Injector $injector
    ) {
        $this->config = $configLoader->load('horde');
        $this->healthCheck = new HealthCheckService($injector);
    }

    /**
     * Handle health check requests
     *
     * Routes:
     * - GET /api/v1/admin/health/{subsystem}
     *
     * Subsystems: db, cache, session, logger, auth, jwt, all
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Authenticate
        if (!$this->authenticate($request)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Invalid or missing admin_secret',
                ],
            ], 401);
        }

        $method = $request->getMethod();
        $route = $request->getAttribute('route');
        $subsystem = $route['subsystem'] ?? 'all';

        if ($method !== 'GET') {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Only GET method is supported',
                ],
            ], 405);
        }

        try {
            $result = match ($subsystem) {
                'db', 'database' => $this->healthCheck->checkDatabase(),
                'cache' => $this->healthCheck->checkCache(),
                'session' => $this->healthCheck->checkSession(),
                'logger', 'log' => $this->healthCheck->checkLogger(),
                'auth' => $this->healthCheck->checkAuth(),
                'jwt' => $this->healthCheck->checkJwt(),
                'all' => $this->healthCheck->checkAll(),
                default => [
                    'status' => 'error',
                    'message' => 'Unknown subsystem: ' . $subsystem,
                    'details' => [
                        'valid_subsystems' => ['db', 'cache', 'session', 'logger', 'auth', 'jwt', 'all'],
                    ],
                ],
            };

            if (isset($result['status']) && $result['status'] === 'error') {
                $statusCode = 500;
            } elseif (isset($result['status']) && $result['status'] === 'warning') {
                $statusCode = 200; // Warning is still 200, just not fully optimal
            } else {
                $statusCode = 200;
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $result,
            ], $statusCode);
        } catch (\Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }
}
