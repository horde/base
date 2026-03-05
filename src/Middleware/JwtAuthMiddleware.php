<?php

declare(strict_types=1);

namespace Horde\Horde\Middleware;

use Horde\Core\Auth\Jwt\VerifiedJwt;
use Horde\Horde\Service\JwtService;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;
use InvalidArgumentException;

/**
 * JWT Authentication Middleware
 *
 * Verifies JWT tokens from Authorization header and adds user info to request.
 * Falls back to session authentication if no JWT token is present.
 * This allows dual-mode authentication without breaking existing session-based auth.
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
class JwtAuthMiddleware implements MiddlewareInterface
{
    /**
     * @param JwtService $jwtService JWT service for token verification
     * @param bool $required Whether JWT authentication is required (default: false)
     *   If true, requests without valid JWT will be rejected.
     *   If false, falls back to session authentication.
     */
    public function __construct(
        private readonly JwtService $jwtService,
        private readonly bool $required = false
    ) {}

    /**
     * Process an incoming server request
     *
     * @param ServerRequestInterface $request
     * @param RequestHandlerInterface $handler
     * @return ResponseInterface
     */
    public function process(
        ServerRequestInterface $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        // Try to extract JWT token from Authorization header
        $authHeader = $request->getHeaderLine('Authorization');
        $token = $authHeader ? $this->jwtService->extractTokenFromHeader($authHeader) : null;

        // If no token and JWT is required, reject request
        if ($token === null && $this->required) {
            return $this->unauthorizedResponse('JWT token required');
        }

        // If no token but JWT not required, fall back to session auth
        if ($token === null) {
            // Let the request continue - session auth will handle it
            return $handler->handle($request);
        }

        // Verify the JWT token
        try {
            $verified = $this->jwtService->verifyAccessToken($token);

            // Add verified JWT and user info to request attributes
            $request = $request
                ->withAttribute('jwt', $verified)
                ->withAttribute('jwt_user_id', $verified->getSubject())
                ->withAttribute('jwt_claims', $verified->claims)
                ->withAttribute('auth_type', 'jwt');

            // Extract Horde-specific claims if present
            if ($verified->hasClaim('horde')) {
                $hordeClaims = $verified->getClaim('horde');
                if (is_array($hordeClaims)) {
                    $request = $request->withAttribute('jwt_horde_claims', $hordeClaims);
                }
            }

            return $handler->handle($request);
        } catch (InvalidArgumentException $e) {
            // Invalid token
            if ($this->required) {
                return $this->unauthorizedResponse('Invalid JWT token: ' . $e->getMessage());
            }

            // Fall back to session auth if JWT not required
            return $handler->handle($request);
        }
    }

    /**
     * Create an unauthorized response
     *
     * @param string $message Error message
     * @return ResponseInterface
     */
    private function unauthorizedResponse(string $message): ResponseInterface
    {
        $response = new Response();
        $response = $response->withStatus(401);
        $response->getBody()->write(json_encode([
            'error' => 'unauthorized',
            'message' => $message,
        ]));

        return $response->withHeader('Content-Type', 'application/json');
    }
}
