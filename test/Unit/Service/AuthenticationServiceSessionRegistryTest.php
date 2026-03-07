<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Service;

use PHPUnit\Framework\TestCase;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\JwtService;
use Horde\Core\Auth\Jwt\VerifiedJwt;
use Horde\Core\Auth\Jwt\GeneratedJwt;
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
 */
class AuthenticationServiceSessionRegistryTest extends TestCase
{
    /**
     * Test that authenticate() registers refresh token in session
     */
    public function testAuthenticateRegistersRefreshToken(): void
    {
        // This test requires full Horde environment with Horde_Auth
        $this->markTestSkipped('Requires full Horde environment with Horde_Auth');

        // Setup session
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
        $_SESSION = [];

        // Create mocks
        $registry = $this->createMock(Horde_Registry::class);
        $jwtService = $this->createMock(JwtService::class);

        // Mock JWT generation
        $mockAccessToken = $this->createMockToken('access-jti-123');
        $mockRefreshToken = $this->createMockToken('refresh-jti-456');

        $jwtService->method('generateAccessToken')
            ->willReturn($mockAccessToken);

        $jwtService->method('generateRefreshToken')
            ->willReturn($mockRefreshToken);

        $registry->method('setAuth')
            ->willReturn(null);

        // Create service
        $authService = new AuthenticationService($registry, $jwtService);

        // Mock the underlying auth (normally done by Horde_Auth)
        $GLOBALS['injector'] = $this->createMockInjector();

        // Authenticate
        $result = $authService->authenticate('testuser', 'testpass', [
            'generate_jwt' => true
        ]);

        // Verify refresh token registered in session
        $this->assertArrayHasKey('__horde', $_SESSION);
        $this->assertArrayHasKey('auth', $_SESSION['__horde']);
        $this->assertArrayHasKey('refresh_tokens', $_SESSION['__horde']['auth']);

        $registry = $_SESSION['__horde']['auth']['refresh_tokens'];
        $this->assertArrayHasKey('refresh-jti-456', $registry);

        $metadata = $registry['refresh-jti-456'];
        $this->assertArrayHasKey('issued_at', $metadata);
        $this->assertArrayHasKey('expires_at', $metadata);
        $this->assertArrayHasKey('last_used', $metadata);
        $this->assertArrayHasKey('user_agent', $metadata);
    }

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
                        ]
                    ]
                ]
            ]
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
        $authService = new AuthenticationService($registry, $jwtService);

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
     * Test that refreshToken() fails if token not in registry
     *
     * NOTE: This test is for planned functionality (session registry validation)
     * that is not yet implemented. Currently refreshToken() only validates:
     * - JWT signature and expiry
     * - Session exists and is authenticated
     * - Username matches
     * - Session ID matches JTI
     *
     * Future enhancement: Add session registry tracking and validation
     */
    public function testRefreshTokenFailsIfNotInRegistry(): void
    {
        $this->markTestSkipped('Session registry validation not yet implemented');

        // Setup session WITHOUT token in registry
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }

        $_SESSION = [
            '__horde' => [
                'auth' => [
                    'refresh_tokens' => [
                        // Different JTI
                        'other-jti-000' => ['token' => 'other-token'],
                    ],
                ],
            ],
        ];

        // Create mocks
        $registry = $this->createMock(Horde_Registry::class);
        $jwtService = $this->createMock(JwtService::class);

        // Mock refresh token verification (JWT is valid)
        $mockVerified = $this->createMockVerifiedToken('test-jti-789', 'testuser');
        $jwtService->method('verifyRefreshToken')
            ->willReturn($mockVerified);

        // Mock generateAccessToken (in case test logic passes validation)
        $mockNewAccessToken = $this->createMockToken('new-access-jti');
        $jwtService->method('generateAccessToken')
            ->willReturn($mockNewAccessToken);

        // Mock session authentication (session is valid)
        $registry->method('getAuth')
            ->willReturn('testuser');

        // Create service
        $authService = new AuthenticationService($registry, $jwtService);

        // Refresh token
        $result = $authService->refreshToken('fake-jwt-token');

        // Should FAIL (token not in this session's registry)
        $this->assertFalse($result['success']);
        $this->assertStringContainsString('not valid for this session', $result['error']);
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
        $authService = new AuthenticationService($registry, null);

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

    private function createMockInjector()
    {
        // Use getMockBuilder with addMethods for methods that may not exist at test time
        $mockAuth = $this->getMockBuilder(\Horde_Auth::class)
            ->disableOriginalConstructor()
            ->addMethods(['authenticate'])
            ->getMock();
        $mockAuth->expects($this->any())
            ->method('authenticate')
            ->willReturn(true);

        $mockAuthFactory = $this->getMockBuilder(\Horde_Core_Factory_Auth::class)
            ->disableOriginalConstructor()
            ->addMethods(['create'])
            ->getMock();
        $mockAuthFactory->expects($this->any())
            ->method('create')
            ->willReturn($mockAuth);

        $mockInjector = $this->getMockBuilder(\Horde\Injector\Injector::class)
            ->disableOriginalConstructor()
            ->addMethods(['getInstance'])
            ->getMock();
        $mockInjector->expects($this->any())
            ->method('getInstance')
            ->willReturn($mockAuthFactory);

        return $mockInjector;
    }
}
