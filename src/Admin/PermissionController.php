<?php

declare(strict_types=1);

namespace Horde\Horde\Admin;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Service\PermissionService;
use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Horde\Traits\JsonResponseTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Permission REST API controller
 *
 * Manages Horde permissions via admin API.
 * Permissions can be:
 * - matrix: Bitmask (SHOW=2, READ=4, EDIT=8, DELETE=16)
 * - boolean: Simple on/off
 * - int: Numeric quotas/limits
 *
 * Endpoints:
 * - GET /api/v1/admin/permissions - List all permissions
 * - GET /api/v1/admin/permissions/{name} - Get permission details
 * - POST /api/v1/admin/permissions - Create permission
 * - PATCH /api/v1/admin/permissions/{name} - Update permission
 * - DELETE /api/v1/admin/permissions/{name} - Delete permission
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
class PermissionController implements RequestHandlerInterface
{
    use JsonResponseTrait;
    use AdminAuthenticationTrait;

    private PermissionService $permissionService;

    public function __construct(
        ConfigLoader $configLoader,
        PermissionService $permissionService
    ) {
        $this->config = $configLoader->load('horde');
        $this->permissionService = $permissionService;
    }

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
        $name = $route['name'] ?? '';

        // Dispatch to appropriate action
        return match ($action) {
            'list' => $this->list($request),
            'get' => $this->get($request, $name),
            'create' => $this->create($request),
            'update' => $this->update($request, $name),
            'delete' => $this->delete($request, $name),
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
     * List all permissions
     *
     * GET /api/v1/admin/permissions
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function list(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $names = $this->permissionService->listAll();

            // Get full details for each permission
            $permissions = [];
            foreach ($names as $name) {
                try {
                    $perm = $this->permissionService->get($name);
                    $permissions[] = $perm;
                } catch (\Exception $e) {
                    // Skip permissions that can't be retrieved
                    continue;
                }
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $permissions,
            ]);
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

    /**
     * Get single permission by name
     *
     * GET /api/v1/admin/permissions/{name}
     *
     * @param ServerRequestInterface $request
     * @param string $name
     * @return ResponseInterface
     */
    private function get(ServerRequestInterface $request, string $name): ResponseInterface
    {
        try {
            $name = urldecode($name);

            if (!$this->permissionService->exists($name)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'PERMISSION_NOT_FOUND',
                        'message' => "Permission '$name' not found",
                    ],
                ], 404);
            }

            $permission = $this->permissionService->get($name);

            return $this->jsonResponse([
                'success' => true,
                'data' => $permission,
            ]);
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

    /**
     * Create new permission
     *
     * POST /api/v1/admin/permissions
     * Body: {
     *   "name": "horde:administration:permissions",
     *   "type": "matrix",
     *   "data": {
     *     "guest": 0,
     *     "default": 2,
     *     "creator": 30,
     *     "users": [{"id": "admin", "permissions": 30}],
     *     "groups": [{"id": "1", "name": "admins", "permissions": 30}]
     *   }
     * }
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            // Parse JSON body
            $body = json_decode((string) $request->getBody(), true);
            if (!is_array($body)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Invalid JSON body',
                    ],
                ], 400);
            }

            // Validate required fields
            if (empty($body['name'])) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing required field: name',
                    ],
                ], 400);
            }

            $name = $body['name'];
            $type = $body['type'] ?? 'matrix';
            $data = $body['data'] ?? [];

            // Check if permission already exists
            if ($this->permissionService->exists($name)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'PERMISSION_EXISTS',
                        'message' => "Permission '$name' already exists",
                    ],
                ], 409);
            }

            // Create permission
            $this->permissionService->create($name, $type, $data);

            // Retrieve created permission
            $permission = $this->permissionService->get($name);

            return $this->jsonResponse([
                'success' => true,
                'data' => $permission,
            ], 201);
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

    /**
     * Update permission
     *
     * PATCH /api/v1/admin/permissions/{name}
     * Body: {
     *   "data": {
     *     "guest": 0,
     *     "default": 2,
     *     "users": [...],
     *     "groups": [...]
     *   }
     * }
     *
     * @param ServerRequestInterface $request
     * @param string $name
     * @return ResponseInterface
     */
    private function update(ServerRequestInterface $request, string $name): ResponseInterface
    {
        try {
            $name = urldecode($name);

            if (!$this->permissionService->exists($name)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'PERMISSION_NOT_FOUND',
                        'message' => "Permission '$name' not found",
                    ],
                ], 404);
            }

            // Parse JSON body
            $body = json_decode((string) $request->getBody(), true);
            if (!is_array($body)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Invalid JSON body',
                    ],
                ], 400);
            }

            // Validate required fields
            // Accept both wrapped {'data': {...}} and direct {...} formats
            $data = $body['data'] ?? $body;

            if (!is_array($data) || empty($data)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Permission data must be a non-empty object',
                    ],
                ], 400);
            }

            // Update permission
            $this->permissionService->update($name, $data);

            // Retrieve updated permission
            $permission = $this->permissionService->get($name);

            return $this->jsonResponse([
                'success' => true,
                'data' => $permission,
            ]);
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

    /**
     * Delete permission
     *
     * DELETE /api/v1/admin/permissions/{name}?force=true
     *
     * @param ServerRequestInterface $request
     * @param string $name
     * @return ResponseInterface
     */
    private function delete(ServerRequestInterface $request, string $name): ResponseInterface
    {
        try {
            $name = urldecode($name);

            if (!$this->permissionService->exists($name)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'PERMISSION_NOT_FOUND',
                        'message' => "Permission '$name' not found",
                    ],
                ], 404);
            }

            // Get force parameter from query string
            $queryParams = $request->getQueryParams();
            $force = !empty($queryParams['force']);

            // Delete permission
            $this->permissionService->delete($name, $force);

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'name' => $name,
                    'message' => 'Permission deleted successfully',
                ],
            ]);
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
