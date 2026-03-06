<?php

declare(strict_types=1);

namespace Horde\Horde\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * JWT Session Middleware
 *
 * Runs BEFORE HordeCoreMiddleware to set custom session ID from JWT.
 * If a valid JWT refresh token is present in cookie, use its JTI as session ID.
 *
 * This allows the session to be created with the correct ID before Horde's
 * session management starts, avoiding session ID regeneration issues.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class JwtSession implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check for JWT refresh token in cookie
        $cookies = $request->getCookieParams();
        $jwtRefreshToken = $cookies['horde_jwt_refresh'] ?? null;

        \Horde::log("JwtSession middleware: horde_jwt_refresh cookie " . ($jwtRefreshToken ? "present" : "absent"), 'DEBUG');

        if ($jwtRefreshToken) {
            // Parse JWT to get JTI (without full validation - just decode)
            $parts = explode('.', $jwtRefreshToken);
            if (count($parts) === 3) {
                try {
                    $payload = json_decode(
                        base64_decode(strtr($parts[1], '-_', '+/')),
                        true,
                        512,
                        JSON_THROW_ON_ERROR
                    );

                    if (isset($payload['jti']) && isset($payload['type']) && $payload['type'] === 'refresh') {
                        $jti = $payload['jti'];

                        // Set session ID BEFORE any session is started
                        if (session_status() === PHP_SESSION_NONE) {
                            session_id($jti);
                            \Horde::log("JwtSession middleware: Set session_id to JTI: $jti", 'DEBUG');
                            // Session will be started by HordeCoreMiddleware
                        }
                    }
                } catch (\Exception $e) {
                    // Invalid JWT, ignore and let normal session handling proceed
                    \Horde::log("JwtSession middleware: Failed to parse JWT: " . $e->getMessage(), 'DEBUG');
                }
            }
        }

        // Continue to next middleware (HordeCoreMiddleware will start session)
        return $handler->handle($request);
    }
}
