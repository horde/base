<?php

declare(strict_types=1);

namespace Horde\Horde\Admin;

use Horde\Core\Service\IdentityService;
use Horde\Core\Service\IdentityNotFoundException;
use Horde\Horde\Traits\JsonResponseTrait;
use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;
use Exception;

/**
 * Identity management REST API controller
 *
 * Handles CRUD operations for user identities via admin API.
 * Identities are first-class resources independent of user existence.
 *
 * Endpoints:
 * - GET /api/v1/admin/identities/{username} - List user's identities
 * - POST /api/v1/admin/identities/{username} - Create identity
 * - GET /api/v1/admin/identities/{username}/{index} - Get single identity
 * - PUT /api/v1/admin/identities/{username}/{index} - Update identity
 * - DELETE /api/v1/admin/identities/{username}/{index} - Delete identity
 * - PATCH /api/v1/admin/identities/{username}/{index}/default - Set as default
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
class IdentityController implements RequestHandlerInterface
{
    use JsonResponseTrait;
    use AdminAuthenticationTrait;

    private IdentityService $identityService;

    /**
     * Constructor - inject dependencies
     */
    public function __construct(
        ConfigLoader $configLoader,
        IdentityService $identityService
    ) {
        $this->config = $configLoader->load('horde');
        $this->identityService = $identityService;
    }

    /**
     * Handle identity API requests
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
        $username = $route['username'] ?? '';
        $index = isset($route['index']) ? (int) $route['index'] : null;

        // Dispatch to appropriate action
        return match ($action) {
            'list' => $this->list($request, $username),
            'create' => $this->create($request, $username),
            'get' => $this->get($request, $username, $index),
            'update' => $this->update($request, $username, $index),
            'delete' => $this->delete($request, $username, $index),
            'setDefault' => $this->setDefault($request, $username, $index),
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
     * List all identities for user
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @return ResponseInterface
     */
    private function list(ServerRequestInterface $request, string $username): ResponseInterface
    {
        try {
            $username = urldecode($username);
            $identities = $this->identityService->getAll($username);
            $default = $this->identityService->getDefault($username);

            // Add index to each identity
            $indexed = [];
            foreach ($identities as $index => $identity) {
                $indexed[] = array_merge(['index' => $index], $identity);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'default_identity' => $default,
                    'identities' => $indexed,
                ],
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
     * Create new identity
     *
     * Body: { "id": "Work", "fullname": "John Doe", "from_addr": "john@work.com", ... }
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @return ResponseInterface
     */
    private function create(ServerRequestInterface $request, string $username): ResponseInterface
    {
        try {
            $username = urldecode($username);

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
            if (empty($body['id']) || empty($body['from_addr'])) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing required fields: id, from_addr',
                    ],
                ], 400);
            }

            // Create identity
            $index = $this->identityService->add($username, $body);

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'index' => $index,
                    'identity' => array_merge(['index' => $index], $body),
                ],
            ], 201);
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
     * Get single identity by index
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @param int|null $index
     * @return ResponseInterface
     */
    private function get(ServerRequestInterface $request, string $username, ?int $index): ResponseInterface
    {
        try {
            $username = urldecode($username);

            if ($index === null) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing identity index',
                    ],
                ], 400);
            }

            $identity = $this->identityService->get($username, $index);
            if (!$identity) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'IDENTITY_NOT_FOUND',
                        'message' => "Identity $index not found for user $username",
                    ],
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'index' => $index,
                    'identity' => array_merge(['index' => $index], $identity),
                ],
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
     * Update identity
     *
     * Body: { "id": "Work", "fullname": "John Doe", "from_addr": "john@work.com", ... }
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @param int|null $index
     * @return ResponseInterface
     */
    private function update(ServerRequestInterface $request, string $username, ?int $index): ResponseInterface
    {
        try {
            $username = urldecode($username);

            if ($index === null) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing identity index',
                    ],
                ], 400);
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

            // Update identity
            try {
                $this->identityService->update($username, $index, $body);
            } catch (IdentityNotFoundException $e) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'IDENTITY_NOT_FOUND',
                        'message' => $e->getMessage(),
                    ],
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'index' => $index,
                    'identity' => array_merge(['index' => $index], $body),
                ],
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
     * Delete identity
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @param int|null $index
     * @return ResponseInterface
     */
    private function delete(ServerRequestInterface $request, string $username, ?int $index): ResponseInterface
    {
        try {
            $username = urldecode($username);

            if ($index === null) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing identity index',
                    ],
                ], 400);
            }

            // Delete identity
            try {
                $this->identityService->delete($username, $index);
            } catch (IdentityNotFoundException $e) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'IDENTITY_NOT_FOUND',
                        'message' => $e->getMessage(),
                    ],
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'index' => $index,
                    'message' => 'Identity deleted successfully',
                ],
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
     * Set identity as default
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @param int|null $index
     * @return ResponseInterface
     */
    private function setDefault(ServerRequestInterface $request, string $username, ?int $index): ResponseInterface
    {
        try {
            $username = urldecode($username);

            if ($index === null) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing identity index',
                    ],
                ], 400);
            }

            // Set as default
            try {
                $this->identityService->setDefault($username, $index);
            } catch (IdentityNotFoundException $e) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'IDENTITY_NOT_FOUND',
                        'message' => $e->getMessage(),
                    ],
                ], 404);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'default_identity' => $index,
                    'message' => 'Default identity updated',
                ],
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
