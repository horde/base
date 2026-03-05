<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Horde\Horde\Service\AuthenticationService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;

/**
 * Authentication API Controller
 *
 * RESTful API endpoints for authentication:
 * - POST /api/v1/auth/login - Authenticate and get tokens
 * - POST /api/v1/auth/refresh - Refresh access token
 * - POST /api/v1/auth/logout - Log out (destroy session)
 *
 * Returns JWT tokens for responsive UI while maintaining session compatibility.
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
class AuthApiController implements RequestHandlerInterface
{
    private AuthenticationService $authService;

    /**
     * Constructor - gets dependencies from global injector
     */
    public function __construct()
    {
        // Get AuthenticationService from injector
        // This is needed because rampage routing doesn't support constructor injection
        $injector = $GLOBALS['injector'] ?? null;
        if (!$injector) {
            throw new \RuntimeException('Injector not available');
        }

        // Try to get AuthenticationService from injector
        try {
            $this->authService = $injector->getInstance(AuthenticationService::class);
            file_put_contents('/tmp/auth-constructor-debug.txt', "Got AuthenticationService from injector\n", FILE_APPEND);
        } catch (\Exception $e) {
            // If JWT bootstrap wasn't loaded, create service manually
            $registry = $injector->getInstance('Horde_Registry');
            $this->authService = new AuthenticationService($registry, null);
            file_put_contents('/tmp/auth-constructor-debug.txt', "Created AuthenticationService manually (no JWT): " . $e->getMessage() . "\n", FILE_APPEND);
        }
    }

    /**
     * Handle authentication API requests
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $method = strtoupper($request->getMethod());
        $path = $request->getUri()->getPath();

        // Route to appropriate action
        if ($method === 'POST' && str_ends_with($path, '/login')) {
            return $this->login($request);
        }

        if ($method === 'POST' && str_ends_with($path, '/refresh')) {
            return $this->refresh($request);
        }

        if ($method === 'POST' && str_ends_with($path, '/logout')) {
            return $this->logout($request);
        }

        return $this->jsonResponse(['error' => 'Not found'], 404);
    }

    /**
     * Login endpoint: POST /api/v1/auth/login
     *
     * Request body:
     * {
     *   "username": "user@example.com",
     *   "password": "secret",
     *   "audience": ["webmail", "tasks"] // optional
     * }
     *
     * Response (success):
     * {
     *   "access_token": "eyJ...",
     *   "refresh_token": "eyJ...",
     *   "token_type": "Bearer",
     *   "expires_at": 1234567890,
     *   "user_id": "user@example.com"
     * }
     *
     * Response (failure):
     * {
     *   "error": "Invalid credentials"
     * }
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function login(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parseJsonBody($request);

        if (!isset($body['username']) || !isset($body['password'])) {
            return $this->jsonResponse([
                'error' => 'Missing required fields: username, password'
            ], 400);
        }

        $username = trim($body['username']);
        $password = $body['password'];

        if (empty($username) || empty($password)) {
            return $this->jsonResponse([
                'error' => 'Username and password cannot be empty'
            ], 400);
        }

        // Prepare authentication options
        $options = [
            'generate_jwt' => true,
        ];

        if (isset($body['audience'])) {
            $options['jwt_aud'] = $body['audience'];
        }

        // Authenticate
        $result = $this->authService->authenticate($username, $password, $options);

        // DEBUG
        file_put_contents('/tmp/auth-result-debug.txt', sprintf(
            "Options: %s\nResult: %s\n",
            print_r($options, true),
            print_r($result, true)
        ));

        if (!$result['success']) {
            return $this->jsonResponse([
                'error' => $result['error'] ?? 'Authentication failed'
            ], 401);
        }

        // Return success response
        $response = [
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'] ?? 'Bearer',
            'expires_at' => $result['expires_at'],
            'user_id' => $result['user_id'],
        ];

        if (isset($result['refresh_token'])) {
            $response['refresh_token'] = $result['refresh_token'];
        }

        return $this->jsonResponse($response, 200);
    }

    /**
     * Refresh token endpoint: POST /api/v1/auth/refresh
     *
     * Request body:
     * {
     *   "refresh_token": "eyJ..."
     * }
     *
     * Response (success):
     * {
     *   "access_token": "eyJ...",
     *   "token_type": "Bearer",
     *   "expires_at": 1234567890
     * }
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function refresh(ServerRequestInterface $request): ResponseInterface
    {
        $body = $this->parseJsonBody($request);

        if (!isset($body['refresh_token'])) {
            return $this->jsonResponse([
                'error' => 'Missing required field: refresh_token'
            ], 400);
        }

        $result = $this->authService->refreshToken($body['refresh_token']);

        if (!$result['success']) {
            return $this->jsonResponse([
                'error' => $result['error'] ?? 'Token refresh failed'
            ], 401);
        }

        return $this->jsonResponse([
            'access_token' => $result['access_token'],
            'token_type' => $result['token_type'] ?? 'Bearer',
            'expires_at' => $result['expires_at'],
        ], 200);
    }

    /**
     * Logout endpoint: POST /api/v1/auth/logout
     *
     * Destroys the session. Note: JWT tokens remain valid until expiry
     * unless a token blacklist is implemented.
     *
     * Response:
     * {
     *   "success": true
     * }
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function logout(ServerRequestInterface $request): ResponseInterface
    {
        $this->authService->logout();

        return $this->jsonResponse([
            'success' => true,
            'message' => 'Logged out successfully'
        ], 200);
    }

    /**
     * Parse JSON request body
     *
     * @param ServerRequestInterface $request
     * @return array
     */
    private function parseJsonBody(ServerRequestInterface $request): array
    {
        $contentType = $request->getHeaderLine('Content-Type');
        $body = (string) $request->getBody();

        if (str_contains($contentType, 'application/json')) {
            $decoded = json_decode($body, true);

            // DEBUG
            file_put_contents('/tmp/parse-debug.txt', sprintf(
                "Content-Type: %s\nBody: %s\nDecoded: %s\nJSON Error: %s\n",
                $contentType,
                $body,
                print_r($decoded, true),
                json_last_error_msg()
            ));

            if (json_last_error() === JSON_ERROR_NONE) {
                return $decoded;
            }
        }

        return [];
    }

    /**
     * Create JSON response
     *
     * @param array $data Response data
     * @param int $status HTTP status code
     * @return ResponseInterface
     */
    private function jsonResponse(array $data, int $status = 200): ResponseInterface
    {
        $response = new Response();
        $response = $response->withStatus($status);
        $response = $response->withHeader('Content-Type', 'application/json');
        $response->getBody()->write(json_encode($data, JSON_THROW_ON_ERROR));

        return $response;
    }
}
