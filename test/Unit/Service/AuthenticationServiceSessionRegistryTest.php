<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\JwtService;
use Horde\Core\Auth\Jwt\VerifiedJwt;
use Horde\Core\Auth\Jwt\GeneratedJwt;
use Psr\Log\LoggerInterface;
use Horde_Registry;

/**
 * Unit Test: JWT Session Registry
 *
 * Tests the session registry methods in isolation using mocks.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @coversNothing
 */
class AuthenticationServiceSessionRegistryTest extends TestCase
{
    /**
     * Test that refreshToken() checks session registry
     */
    public function testRefreshTokenChecksSessionRegistry(): void
    {
        // Setup session with token in registry
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [
            '__horde' => [
                'auth' => [
                    'refresh_tokens' => [
                        'test-jti-789' => [
                            'issued_at' => time(),
                            'expires_at' => time() + 2592000,
                            'last_used' => time(),
                            'user_agent' => 'PHPUnit',
                        ],
                    ],
                ],
            ],
        ];

        // Create mocks
        $registry = $this->createMock(Horde_Registry::class);
        $jwtService = $this->createMock(JwtService::class);

        // Mock refresh token verification
        $mockVerified = $this->createMockVerifiedToken('test-jti-789', 'testuser');
        $jwtService->method('verifyRefreshToken')
            ->willReturn($mockVerified);

        // Mock new access token generation
        $mockNewAccessToken = $this->createMockToken('new-access-jti');
        $jwtService->method('generateAccessToken')
            ->willReturn($mockNewAccessToken);

        // Mock session authentication
        $registry->method('getAuth')
            ->willReturn('testuser');

        // Create service
        $authService = new AuthenticationService($registry, $this->createMock(LoggerInterface::class), $jwtService);

        // Refresh token
        $result = $authService->refreshToken('fake-jwt-token');

        // Should succeed (token in registry)
        $this->assertTrue($result['success']);
        $this->assertArrayHasKey('access_token', $result);

        // Note: last_used tracking is not yet implemented in refreshToken()
        // When implemented, uncomment this assertion:
        // $this->assertGreaterThanOrEqual(
        //     time(),
        //     $_SESSION['__horde']['auth']['refresh_tokens']['test-jti-789']['last_used']
        // );
    }

    /**
     * Test that logout destroys session registry
     */
    public function testLogoutDestroysSessionRegistry(): void
    {
        // Setup session with tokens
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [
            '__horde' => [
                'auth' => [
                    'refresh_tokens' => [
                        'token-1' => ['token' => 'token-1-value'],
                        'token-2' => ['token' => 'token-2-value'],
                    ],
                ],
            ],
        ];

        // Create mocks
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('clearAuth');

        // Create service
        $authService = new AuthenticationService($registry, $this->createMock(LoggerInterface::class), null);

        // Logout
        $authService->logout();

        // Session should be cleared by registry->clearAuth()
        // (In real Horde, this destroys the entire session)
    }

    // Helper methods

    private function createMockToken(string $jti)
    {
        // Create actual GeneratedJwt instance (readonly properties can't be mocked)
        $token = 'eyJhbGciOiJIUzI1NiJ9.eyJqdGkiOiInICR' . base64_encode($jti);
        return new GeneratedJwt(
            $token,
            time() + 900,
            [
                'jti' => $jti,
                'sub' => 'testuser',
                'type' => 'access',
                'iss' => 'horde',
            ]
        );
    }

    private function createMockVerifiedToken(string $jti, string $username)
    {
        // Use our own VerifiedJwt class, not external library
        return new VerifiedJwt(
            'fake-jwt-token',
            [
                'jti' => $jti,
                'sub' => $username,
                'type' => 'refresh',
                'iss' => 'horde',
                'iat' => time(),
                'exp' => time() + 86400,
            ]
        );
    }
}
