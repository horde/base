<?php

declare(strict_types=1);

namespace Horde\Horde\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Exception;
use Horde;

/**
 * JWT Session Middleware
 *
 * Runs BEFORE HordeCoreMiddleware to set custom session ID from JWT.
 * If a valid JWT refresh token is present in cookie, use its JTI as session ID.
 *
 * This allows the session to be created with the correct ID before Horde's
 * session management starts, avoiding session ID regeneration issues.
 *
 * Code outside horde/base should use the equivalent class
 * {@see Horde\Core\Middleware\JwtSession} from horde/core.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class JwtSession implements MiddlewareInterface
{
    public function __construct(
        private readonly LoggerInterface $logger,
    ) {}

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        // Check for JWT refresh token in cookie
        $cookies = $request->getCookieParams();
        $jwtRefreshToken = $cookies['horde_jwt_refresh'] ?? null;

        $this->logger->debug('JWT session middleware checking refresh token', [
            'has_cookie' => $jwtRefreshToken !== null,
            'session_status' => session_status(),
        ]);

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
                            $this->logger->debug('JWT session ID set from refresh token', [
                                'jti' => $jti,
                                'previous_session_status' => 'none',
                            ]);
                            // Session will be started by HordeCoreMiddleware
                        }
                    }
                } catch (Exception $e) {
                    // Invalid JWT, ignore and let normal session handling proceed
                    $this->logger->debug('Failed to parse JWT refresh token', [
                        'exception' => $e->getMessage(),
                        'exception_class' => get_class($e),
                    ]);
                }
            }
        }

        // Continue to next middleware (HordeCoreMiddleware will start session)
        return $handler->handle($request);
    }
}
