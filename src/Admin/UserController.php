<?php

declare(strict_types=1);

namespace Horde\Horde\Admin;

use Horde\Core\Auth\AuthService;
use Horde\Core\Auth\AuthNotSupportedException;
use Horde\Core\Service\IdentityService;
use Horde\Horde\Traits\JsonResponseTrait;
use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde_Auth_Exception;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use RuntimeException;

/**
 * User management REST API controller
 *
 * Handles CRUD operations for users via admin API.
 * Includes embedded identity support for convenience.
 *
 * Endpoints:
 * - GET /api/v1/admin/users - List users (with pagination)
 * - POST /api/v1/admin/users - Create user
 * - GET /api/v1/admin/users/{username} - Get single user
 * - PATCH /api/v1/admin/users/{username} - Update user
 * - DELETE /api/v1/admin/users/{username} - Delete user
 * - PATCH /api/v1/admin/users/{username}/password - Change password
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
class UserController implements RequestHandlerInterface
{
    use JsonResponseTrait;
    use AdminAuthenticationTrait;

    private AuthService $authService;
    private IdentityService $identityService;

    /**
     * Constructor - inject dependencies
     */
    public function __construct(
        ConfigLoader $configLoader,
        AuthService $authService,
        IdentityService $identityService
    ) {
        $this->config = $configLoader->load('horde');
        $this->authService = $authService;
        $this->identityService = $identityService;
    }

    /**
     * Handle user API requests
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

        // Dispatch to appropriate action
        $response = match ($action) {
            'list' => $this->list($request),
            'create' => $this->create($request),
            'get' => $this->get($request, $username),
            'delete' => $this->delete($request, $username),
            'updatePassword' => $this->updatePassword($request, $username),
            default => $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'NOT_FOUND',
                    'message' => 'Endpoint not found',
                ],
            ], 404),
        };

        // Add debug header showing which action handled the request
        return $response->withHeader('X-Admin-Action', $action ?? 'unknown');
    }

    /**
     * Create new user
     *
     * Requires:
     * - username: string (required)
     * - password: string (required)
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function create(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $body = $request->getParsedBody() ?? [];

            // Validate required fields
            $username = $body['username'] ?? null;
            $password = $body['password'] ?? null;

            if (!$username || !$password) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'VALIDATION_ERROR',
                        'message' => 'username and password are required',
                    ],
                ], 400);
            }

            // Check if user already exists
            try {
                if ($this->authService->exists($username)) {
                    return $this->jsonResponse([
                        'success' => false,
                        'error' => [
                            'code' => 'USER_EXISTS',
                            'message' => sprintf('User "%s" already exists', $username),
                        ],
                    ], 409);
                }
            } catch (AuthNotSupportedException $e) {
                // exists() not supported - continue with create attempt
            }

            // Create user via AuthService
            try {
                $this->authService->createUser($username, ['password' => $password]);
            } catch (AuthNotSupportedException $e) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_SUPPORTED',
                        'message' => 'Auth backend does not support user creation',
                    ],
                ], 501);
            } catch (Horde_Auth_Exception $e) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'AUTH_ERROR',
                        'message' => $e->getMessage(),
                    ],
                ], 400);
            }

            // Return created user info
            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'created' => true,
                ],
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
     * List all users with pagination
     *
     * Query params:
     * - page: Page number (default: 1)
     * - per_page: Items per page (default: 50, max: 100)
     * - include_identities: Include identities in response (default: false)
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function list(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $params = $request->getQueryParams();
            $page = max(1, (int) ($params['page'] ?? 1));
            $perPage = min(100, max(1, (int) ($params['per_page'] ?? 50)));
            $includeIdentities = !empty($params['include_identities']);

            // Try to get all users from auth backend
            try {
                $allUsers = $this->authService->listUsers();
            } catch (AuthNotSupportedException $e) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'NOT_SUPPORTED',
                        'message' => 'Auth backend does not support user listing',
                    ],
                ], 501);
            }

            $total = count($allUsers);

            // Calculate pagination
            $offset = ($page - 1) * $perPage;
            $usersPage = array_slice($allUsers, $offset, $perPage);

            // Build user data array
            $users = [];
            foreach ($usersPage as $username) {
                $userData = [
                    'username' => $username,
                ];

                // Optionally include identities
                if ($includeIdentities) {
                    $identities = $this->identityService->getAll($username);
                    $userData['identities'] = $identities;
                    $userData['default_identity'] = $this->identityService->getDefault($username);
                }

                $users[] = $userData;
            }

            $pagination = [
                'total' => $total,
                'page' => $page,
                'per_page' => $perPage,
                'has_next' => ($offset + $perPage) < $total,
                'has_prev' => $page > 1,
            ];

            return $this->jsonResponse([
                'success' => true,
                'data' => $users,
                'pagination' => $pagination,
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
     * Get single user by username
     *
     * Includes identities by default.
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @return ResponseInterface
     */
    private function get(ServerRequestInterface $request, string $username): ResponseInterface
    {
        try {
            $username = urldecode($username);

            // Check if user exists
            if (!$this->authService->exists($username)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => "User '$username' not found",
                    ],
                ], 404);
            }

            // Build user data with identities
            $identities = $this->identityService->getAll($username);
            $userData = [
                'username' => $username,
                'identities' => $identities,
                'default_identity' => $this->identityService->getDefault($username),
            ];

            return $this->jsonResponse([
                'success' => true,
                'data' => $userData,
            ])->withHeader('X-Handler-Method', 'get');
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
     * Change user password
     *
     * Body: { "password": "new_password" }
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @return ResponseInterface
     */
    private function updatePassword(ServerRequestInterface $request, string $username): ResponseInterface
    {
        try {
            $username = urldecode($username);

            // Check if user exists
            if (!$this->authService->exists($username)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => "User '$username' not found",
                    ],
                ], 404);
            }

            // Parse JSON body
            $body = json_decode((string) $request->getBody(), true);
            if (!isset($body['password']) || !is_string($body['password'])) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'INVALID_REQUEST',
                        'message' => 'Missing or invalid password field',
                    ],
                ], 400);
            }

            $newPassword = $body['password'];

            // Update password
            $this->authService->updatePassword($username, $newPassword);

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'message' => 'Password updated successfully',
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

    /**
     * Delete user
     *
     * @param ServerRequestInterface $request
     * @param string $username
     * @return ResponseInterface
     */
    private function delete(ServerRequestInterface $request, string $username): ResponseInterface
    {
        try {
            $username = urldecode($username);

            // Check if user exists
            if (!$this->authService->exists($username)) {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'USER_NOT_FOUND',
                        'message' => "User '$username' not found",
                    ],
                ], 404);
            }

            // Delete user
            $this->authService->deleteUser($username);

            return $this->jsonResponse([
                'success' => true,
                'data' => [
                    'username' => $username,
                    'message' => 'User deleted successfully',
                ],
            ])->withHeader('X-Handler-Method', 'delete');
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
