<?php

declare(strict_types=1);

namespace Horde\Horde\Admin;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\RegistryConfigCompiler;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Service\ApplicationService;
use Horde\Core\Util\VersionReader;
use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Horde\Traits\JsonResponseTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Exception;

/**
 * Admin REST API Controller for introspection endpoints
 *
 * Provides secure remote management interface for Horde metadata.
 * Handles introspection/metadata endpoints only. Resource management
 * (users, identities, groups) handled by dedicated controllers.
 *
 * Endpoints:
 * - POST /api/v1/admin/info - Get Horde installation information
 * - POST /api/v1/admin/applications - List installed applications
 * - GET  /api/v1/admin/registry - Get compiled registry (default + per-vhost)
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
class AdminApiController implements RequestHandlerInterface
{
    use JsonResponseTrait;
    use AdminAuthenticationTrait;

    private ApplicationService $appService;
    private RegistryConfigLoader $registryLoader;
    private RegistryConfigCompiler $registryCompiler;

    /**
     * Constructor - inject dependencies
     */
    public function __construct(
        ConfigLoader $configLoader,
        ApplicationService $appService,
        RegistryConfigLoader $registryLoader,
        RegistryConfigCompiler $registryCompiler,
    ) {
        $this->appService = $appService;
        $this->registryLoader = $registryLoader;
        $this->registryCompiler = $registryCompiler;
        $this->config = $configLoader->load('horde');
    }

    /**
     * Handle admin API requests
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Authenticate with admin_secret
        if (!$this->authenticate($request)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHORIZED',
                    'message' => 'Invalid or missing admin_secret',
                ],
            ], 401);
        }

        // Get matched route parameters from middleware
        $route = $request->getAttribute('route', []);
        $action = $route['action'] ?? null;

        // Dispatch to appropriate action
        return match ($action) {
            'info' => $this->getInfo($request),
            'applications' => $this->getApplications($request),
            'registry' => $this->getRegistry($request),
            default => $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Endpoint not found',
                ],
            ], 404),
        };
    }

    /**
     * Get Horde installation information
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function getInfo(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $registryState = $this->registryLoader->load();
            $hordeApp = $registryState->getApplication('horde');

            // Get version from .horde.yml
            $version = 'unknown';
            if (isset($hordeApp['fileroot'])) {
                $releaseVersion = VersionReader::readVersionFromFileroot($hordeApp['fileroot']);
                if ($releaseVersion) {
                    $version = $releaseVersion;
                }
            }

            $info = [
                'version' => $version,
                'base_path' => $hordeApp['fileroot'] ?? 'unknown',
                'webroot' => $hordeApp['webroot'] ?? 'unknown',
                'applications' => $registryState->listApplications(),
            ];

            return $this->jsonResponse([
                'success' => true,
                'data' => $info,
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get list of installed applications
     *
     * Phase 1: Basic composer + registry config level introspection
     * Uses ApplicationService instead of registry for admin perspective
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function getApplications(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $apps = $this->appService->listApplications();

            return $this->jsonResponse([
                'success' => true,
                'data' => $apps,
            ]);
        } catch (Exception $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INTERNAL_ERROR',
                    'message' => $e->getMessage(),
                ],
            ], 500);
        }
    }

    /**
     * Get compiled registry (default merge plus per-vhost deltas).
     *
     * Response shape mirrors RegistryConfigCompiler::compile():
     *
     *   {
     *     "success": true,
     *     "data": {
     *       "default": { ...merged default registry },
     *       "foo.example.com": { ...delta relative to default },
     *       "bar.example.com": { ...delta relative to default }
     *     }
     *   }
     *
     * Vhost files on disk are auto-discovered by the compiler. Absent
     * vhost files yield no key at all rather than an empty entry, so
     * a deployment with no vhost overrides gets `{"default": {...}}`.
     */
    private function getRegistry(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $compiled = $this->registryCompiler->compile();

            return $this->jsonResponse([
                'success' => true,
                'data' => $compiled,
            ]);
        } catch (Exception $e) {
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
