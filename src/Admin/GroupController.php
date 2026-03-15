<?php

declare(strict_types=1);

namespace Horde\Horde\Admin;

use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Service\GroupService;
use Horde\Core\Service\Exception\GroupNotFoundException;
use Horde\Core\Service\Exception\GroupExistsException;
use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Horde\Traits\JsonResponseTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Admin REST API controller for group management
 */
class GroupController implements RequestHandlerInterface
{
    use JsonResponseTrait;
    use AdminAuthenticationTrait;

    private GroupService $groupService;

    public function __construct(
        ConfigLoader $configLoader,
        GroupService $groupService
    ) {
        $this->config = $configLoader->load('horde');
        $this->groupService = $groupService;
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
        $identifier = $route['identifier'] ?? '';
        $username = $route['username'] ?? '';

        return match ($action) {
            'list' => $this->list($request),
            'get' => $this->get($request, $identifier),
            'create' => $this->create($request),
            'delete' => $this->delete($request, $identifier),
            'getMembers' => $this->getMembers($request, $identifier),
            'addMember' => $this->addMember($request, $identifier),
            'removeMember' => $this->removeMember($request, $identifier, $username),
            'setMembers' => $this->setMembers($request, $identifier),
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
     * List all groups with pagination
     *
     * GET /api/v1/admin/groups?page=1&per_page=50
     *
     * @param ServerRequestInterface $request HTTP request
     * @return ResponseInterface JSON response
     */
    private function list(ServerRequestInterface $request): ResponseInterface
    {
        // Parse pagination parameters
        $queryParams = $request->getQueryParams();
        $page = (int) ($queryParams['page'] ?? 1);
        $perPage = (int) ($queryParams['per_page'] ?? 50);

        // Validate pagination
        if ($page < 1) {
            $page = 1;
        }
        if ($perPage < 1 || $perPage > 100) {
            $perPage = 50;
        }

        $result = $this->groupService->listAll($page, $perPage);

        return $this->jsonResponse([
            'success' => true,
            'data' => array_map(fn($g) => $g->toArray(), $result->groups),
            'pagination' => [
                'page' => $result->page,
                'per_page' => $result->perPage,
                'total' => $result->total,
                'has_next' => $result->hasNext,
                'has_prev' => $result->hasPrev,
            ],
        ]);
    }

    /**
     * Get a specific group by identifier
     *
     * GET /api/v1/admin/groups/:identifier
     *
     * @param ServerRequestInterface $request HTTP request
     * @param string $identifier Group ID or name
     * @return ResponseInterface JSON response
     */
    private function get(ServerRequestInterface $request, string $identifier): ResponseInterface
    {
        try {
            $group = $this->groupService->get($identifier);
            return $this->jsonResponse([
                'success' => true,
                'data' => $group->toArray(),
            ]);
        } catch (GroupNotFoundException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * Create a new group
     *
     * POST /api/v1/admin/groups
     * Body: {"name": "groupname", "members": ["user1", "user2"]}
     *
     * @param ServerRequestInterface $request HTTP request
     * @return ResponseInterface JSON response
     */
    private function create(ServerRequestInterface $request): ResponseInterface
    {
        if ($this->groupService->isReadOnly()) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Group backend is read-only',
                ],
            ], 405);
        }

        $body = json_decode((string) $request->getBody(), true);
        $name = $body['name'] ?? null;
        $members = $body['members'] ?? [];

        if (!$name) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'MISSING_NAME',
                    'message' => 'Group name is required',
                ],
            ], 400);
        }

        try {
            $group = $this->groupService->create($name);

            // Add members if provided (batch or empty)
            if (!empty($members)) {
                $this->groupService->addMembers($group->id, $members);
                // Reload to include members
                $group = $this->groupService->get($group->id);
            }

            return $this->jsonResponse([
                'success' => true,
                'data' => $group->toArray(),
            ], 201);
        } catch (GroupExistsException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_EXISTS',
                    'message' => $e->getMessage(),
                ],
            ], 409);
        }
    }

    /**
     * Delete a group
     *
     * DELETE /api/v1/admin/groups/:identifier
     *
     * @param ServerRequestInterface $request HTTP request
     * @param string $identifier Group ID or name
     * @return ResponseInterface JSON response
     */
    private function delete(ServerRequestInterface $request, string $identifier): ResponseInterface
    {
        if ($this->groupService->isReadOnly()) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Group backend is read-only',
                ],
            ], 405);
        }

        try {
            $this->groupService->delete($identifier);
            return $this->jsonResponse([
                'success' => true,
                'message' => 'Group deleted successfully',
            ]);
        } catch (GroupNotFoundException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * Get group members
     *
     * GET /api/v1/admin/groups/:identifier/members
     *
     * @param ServerRequestInterface $request HTTP request
     * @param string $identifier Group ID or name
     * @return ResponseInterface JSON response
     */
    private function getMembers(ServerRequestInterface $request, string $identifier): ResponseInterface
    {
        try {
            $members = $this->groupService->getMembers($identifier);
            return $this->jsonResponse([
                'success' => true,
                'data' => $members,
            ]);
        } catch (GroupNotFoundException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * Add member(s) to a group
     *
     * POST /api/v1/admin/groups/:identifier/members
     * Body: {"username": "user1"} OR {"usernames": ["user1", "user2"]}
     *
     * @param ServerRequestInterface $request HTTP request
     * @param string $identifier Group ID or name
     * @return ResponseInterface JSON response
     */
    private function addMember(ServerRequestInterface $request, string $identifier): ResponseInterface
    {
        if ($this->groupService->isReadOnly()) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Group backend is read-only',
                ],
            ], 405);
        }

        $body = json_decode((string) $request->getBody(), true);

        try {
            // Support both single and batch operations
            if (isset($body['username'])) {
                // Single member
                $username = $body['username'];
                $this->groupService->addMember($identifier, $username);
            } elseif (isset($body['usernames']) && is_array($body['usernames'])) {
                // Batch members
                $this->groupService->addMembers($identifier, $body['usernames']);
            } else {
                return $this->jsonResponse([
                    'success' => false,
                    'error' => [
                        'code' => 'MISSING_PARAMETER',
                        'message' => 'Either "username" (string) or "usernames" (array) is required',
                    ],
                ], 400);
            }

            // Return updated group
            $group = $this->groupService->get($identifier);
            return $this->jsonResponse([
                'success' => true,
                'data' => $group->toArray(),
            ]);
        } catch (GroupNotFoundException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * Remove a member from a group
     *
     * DELETE /api/v1/admin/groups/:identifier/members/:username
     *
     * @param ServerRequestInterface $request HTTP request
     * @param string $identifier Group ID or name
     * @param string $username Username to remove
     * @return ResponseInterface JSON response
     */
    private function removeMember(
        ServerRequestInterface $request,
        string $identifier,
        string $username
    ): ResponseInterface {
        if ($this->groupService->isReadOnly()) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Group backend is read-only',
                ],
            ], 405);
        }

        try {
            $this->groupService->removeMember($identifier, $username);

            // Return updated group
            $group = $this->groupService->get($identifier);
            return $this->jsonResponse([
                'success' => true,
                'data' => $group->toArray(),
            ]);
        } catch (GroupNotFoundException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }

    /**
     * Replace all members of a group
     *
     * PUT /api/v1/admin/groups/:identifier/members
     * Body: {"usernames": ["user1", "user2"]}
     *
     * @param ServerRequestInterface $request HTTP request
     * @param string $identifier Group ID or name
     * @return ResponseInterface JSON response
     */
    private function setMembers(ServerRequestInterface $request, string $identifier): ResponseInterface
    {
        if ($this->groupService->isReadOnly()) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'METHOD_NOT_ALLOWED',
                    'message' => 'Group backend is read-only',
                ],
            ], 405);
        }

        $body = json_decode((string) $request->getBody(), true);
        // Accept both 'members' and 'usernames' for backward compatibility
        $usernames = $body['members'] ?? $body['usernames'] ?? null;

        if (!is_array($usernames)) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'INVALID_PARAMETER',
                    'message' => 'Parameter "members" or "usernames" must be an array',
                ],
            ], 400);
        }

        try {
            $this->groupService->setMembers($identifier, $usernames);

            // Return updated group
            $group = $this->groupService->get($identifier);
            return $this->jsonResponse([
                'success' => true,
                'data' => $group->toArray(),
            ]);
        } catch (GroupNotFoundException $e) {
            return $this->jsonResponse([
                'success' => false,
                'error' => [
                    'code' => 'GROUP_NOT_FOUND',
                    'message' => $e->getMessage(),
                ],
            ], 404);
        }
    }
}
