<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Middleware;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Middleware\JwtAuthMiddleware;
use Horde\Horde\Service\JwtService;
use Horde\Core\Auth\Jwt\VerifiedJwt;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use InvalidArgumentException;

/**
 * Unit Test: JwtAuthMiddleware
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(JwtAuthMiddleware::class)]
class JwtAuthMiddlewareTest extends TestCase
{
    private JwtService $jwtService;
    private ServerRequestInterface $request;
    private RequestHandlerInterface $handler;
    private ResponseInterface $response;

    protected function setUp(): void
    {
        $this->jwtService = $this->createMock(JwtService::class);
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->handler = $this->createMock(RequestHandlerInterface::class);
        $this->response = $this->createMock(ResponseInterface::class);
    }

    public function testProcessWithNoAuthHeaderAndNotRequired(): void
    {
        // No Authorization header
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('');

        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $middleware = new JwtAuthMiddleware($this->jwtService, required: false);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithNoAuthHeaderAndRequired(): void
    {
        // No Authorization header
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('');

        $this->jwtService->expects($this->never())
            ->method('extractTokenFromHeader');

        $middleware = new JwtAuthMiddleware($this->jwtService, required: true);
        $result = $middleware->process($this->request, $this->handler);

        // Should return 401 Unauthorized
        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(401, $result->getStatusCode());
    }

    public function testProcessWithValidJwtToken(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer valid-jwt-token');

        $this->jwtService->method('extractTokenFromHeader')
            ->with('Bearer valid-jwt-token')
            ->willReturn('valid-jwt-token');

        $verifiedJwt = new VerifiedJwt('valid-jwt-token', [
            'sub' => 'user123',
            'jti' => 'token-id',
            'iss' => 'horde.example.com',
        ]);

        $this->jwtService->method('verifyAccessToken')
            ->with('valid-jwt-token')
            ->willReturn($verifiedJwt);

        // Mock withAttribute calls
        $requestWithJwt = $this->createMock(ServerRequestInterface::class);
        $requestWithUserId = $this->createMock(ServerRequestInterface::class);
        $requestWithClaims = $this->createMock(ServerRequestInterface::class);
        $requestWithAuthType = $this->createMock(ServerRequestInterface::class);

        $this->request->method('withAttribute')
            ->willReturnCallback(function($key, $value) use (
                $requestWithJwt, $requestWithUserId, $requestWithClaims, $requestWithAuthType
            ) {
                if ($key === 'jwt') return $requestWithJwt;
                if ($key === 'jwt_user_id') return $requestWithUserId;
                if ($key === 'jwt_claims') return $requestWithClaims;
                if ($key === 'auth_type') return $requestWithAuthType;
                return $this->request;
            });

        $requestWithJwt->method('withAttribute')->willReturn($requestWithUserId);
        $requestWithUserId->method('withAttribute')->willReturn($requestWithClaims);
        $requestWithClaims->method('withAttribute')->willReturn($requestWithAuthType);

        $this->handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $middleware = new JwtAuthMiddleware($this->jwtService);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithInvalidJwtTokenAndNotRequired(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer invalid-token');

        $this->jwtService->method('extractTokenFromHeader')
            ->with('Bearer invalid-token')
            ->willReturn('invalid-token');

        $this->jwtService->method('verifyAccessToken')
            ->with('invalid-token')
            ->willThrowException(new InvalidArgumentException('Token expired'));

        // Should fall back to session auth
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $middleware = new JwtAuthMiddleware($this->jwtService, required: false);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithInvalidJwtTokenAndRequired(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer invalid-token');

        $this->jwtService->method('extractTokenFromHeader')
            ->with('Bearer invalid-token')
            ->willReturn('invalid-token');

        $this->jwtService->method('verifyAccessToken')
            ->with('invalid-token')
            ->willThrowException(new InvalidArgumentException('Token expired'));

        $middleware = new JwtAuthMiddleware($this->jwtService, required: true);
        $result = $middleware->process($this->request, $this->handler);

        // Should return 401 Unauthorized
        $this->assertInstanceOf(ResponseInterface::class, $result);
        $this->assertEquals(401, $result->getStatusCode());
    }

    public function testProcessWithHordeClaims(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer token-with-horde-claims');

        $this->jwtService->method('extractTokenFromHeader')
            ->willReturn('token-with-horde-claims');

        $verifiedJwt = new VerifiedJwt('token-with-horde-claims', [
            'sub' => 'user123',
            'horde' => [
                'apps' => ['imp', 'ingo'],
                'permissions' => ['admin'],
            ],
        ]);

        $this->jwtService->method('verifyAccessToken')
            ->willReturn($verifiedJwt);

        // Mock withAttribute chain
        $mockRequest = $this->createMock(ServerRequestInterface::class);
        $mockRequest->method('withAttribute')->willReturn($mockRequest);
        $this->request->method('withAttribute')->willReturn($mockRequest);

        $this->handler->expects($this->once())
            ->method('handle')
            ->willReturn($this->response);

        $middleware = new JwtAuthMiddleware($this->jwtService);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessWithNullTokenFromHeader(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('InvalidHeader');

        $this->jwtService->method('extractTokenFromHeader')
            ->with('InvalidHeader')
            ->willReturn(null);

        // Should fall back to session auth
        $this->handler->expects($this->once())
            ->method('handle')
            ->with($this->request)
            ->willReturn($this->response);

        $middleware = new JwtAuthMiddleware($this->jwtService, required: false);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertSame($this->response, $result);
    }

    public function testProcessRequiredModePreventsSessionFallback(): void
    {
        $this->request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('InvalidHeader');

        $this->jwtService->method('extractTokenFromHeader')
            ->willReturn(null);

        // Handler should NOT be called
        $this->handler->expects($this->never())
            ->method('handle');

        $middleware = new JwtAuthMiddleware($this->jwtService, required: true);
        $result = $middleware->process($this->request, $this->handler);

        $this->assertEquals(401, $result->getStatusCode());
    }
}
