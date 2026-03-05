<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Service;

use Horde\Horde\Service\JwtService;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;

#[CoversClass(JwtService::class)]
class JwtServiceTest extends TestCase
{
    private JwtService $service;
    private string $secret = 'test-secret-key-minimum-256-bits-required-for-security';
    private string $issuer = 'test-issuer';

    protected function setUp(): void
    {
        $this->service = new JwtService(
            secret: $this->secret,
            issuer: $this->issuer,
            accessTokenTtl: 3600,
            refreshTokenTtl: 86400
        );
    }

    public function testGenerateAccessToken(): void
    {
        $userId = 'test-user';
        $claims = ['role' => 'admin'];

        $token = $this->service->generateAccessToken($userId, $claims);

        $this->assertNotEmpty($token->token);
        $this->assertGreaterThan(time(), $token->expiresAt);
        $this->assertFalse($token->isExpired());
    }

    public function testGenerateRefreshToken(): void
    {
        $userId = 'test-user';
        $accessToken = 'access-token-jti';

        $token = $this->service->generateRefreshToken($userId, $accessToken);

        $this->assertNotEmpty($token->token);
        $this->assertGreaterThan(time(), $token->expiresAt);
    }

    public function testVerifyAccessToken(): void
    {
        $userId = 'test-user';
        $claims = ['role' => 'admin'];

        // Generate token
        $generated = $this->service->generateAccessToken($userId, $claims);

        // Verify token (don't specify audience in options)
        $verified = $this->service->verifyAccessToken($generated->token);

        $this->assertEquals($userId, $verified->getSubject());
        $this->assertEquals($this->issuer, $verified->getIssuer());
        $this->assertEquals('admin', $verified->getClaim('role'));
        $this->assertEquals('access', $verified->getClaim('type'));
        $this->assertFalse($verified->isExpired());
    }

    public function testVerifyRefreshToken(): void
    {
        $userId = 'test-user';
        $accessJti = 'some-access-token-jti';

        // Generate token
        $generated = $this->service->generateRefreshToken($userId, $accessJti);

        // Verify token
        $verified = $this->service->verifyRefreshToken($generated->token);

        $this->assertEquals($userId, $verified->getSubject());
        $this->assertEquals('refresh', $verified->getClaim('type'));
        $this->assertEquals($accessJti, $verified->getClaim('access_jti'));
    }

    public function testRefreshAccessToken(): void
    {
        $userId = 'test-user';

        // Generate initial tokens
        $accessToken = $this->service->generateAccessToken($userId);
        $refreshToken = $this->service->generateRefreshToken($userId, $accessToken->token);

        // Refresh
        $newAccessToken = $this->service->refreshAccessToken($refreshToken->token);

        $this->assertNotEmpty($newAccessToken->token);
        $this->assertNotEquals($accessToken->token, $newAccessToken->token);
        $this->assertGreaterThan(time(), $newAccessToken->expiresAt);
    }

    public function testVerifyInvalidToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->verifyAccessToken('invalid.token.here');
    }

    public function testVerifyTokenWithWrongSecret(): void
    {
        $userId = 'test-user';

        // Generate with one secret
        $token = $this->service->generateAccessToken($userId);

        // Try to verify with different secret
        $otherService = new JwtService(
            secret: 'different-secret-key-minimum-256-bits-required',
            issuer: $this->issuer
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token signature');

        $otherService->verifyAccessToken($token->token);
    }

    public function testVerifyExpiredToken(): void
    {
        $userId = 'test-user';

        // Generate a normal token
        $token = $this->service->generateAccessToken($userId);

        // Manually create an expired token by manipulating the payload
        $parts = explode('.', $token->token);
        $payload = json_decode(base64_decode(strtr($parts[1], '-_', '+/'), true), true);

        // Set expiry to the past
        $payload['exp'] = time() - 3600;
        $payload['iat'] = time() - 7200;

        // Rebuild token with expired payload
        $header = $parts[0];
        $expiredPayload = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
        $dataToSign = "$header.$expiredPayload";
        $signature = rtrim(strtr(base64_encode(hash_hmac('sha256', $dataToSign, $this->secret, true)), '+/', '-_'), '=');
        $expiredToken = "$dataToSign.$signature";

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Token has expired');

        $this->service->verifyAccessToken($expiredToken);
    }

    public function testRefreshWithInvalidToken(): void
    {
        $this->expectException(\InvalidArgumentException::class);

        $this->service->refreshAccessToken('invalid.refresh.token');
    }

    public function testVerifyTokenWithMismatchedIssuer(): void
    {
        $userId = 'test-user';
        $token = $this->service->generateAccessToken($userId);

        // Service with different issuer
        $otherService = new JwtService(
            secret: $this->secret,
            issuer: 'different-issuer'
        );

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid token issuer');

        $otherService->verifyAccessToken($token->token);
    }
}
