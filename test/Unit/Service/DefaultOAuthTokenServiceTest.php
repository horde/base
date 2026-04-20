<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde\Test\Unit\Service;

use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Core\Service\Exception\OAuthTokenRefreshException;
use Horde\Core\Service\OauthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Horde\Service\DefaultOAuthTokenService;
use Horde\Oauth\Client\TokenSet;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

#[CoversClass(DefaultOAuthTokenService::class)]
final class DefaultOAuthTokenServiceTest extends TestCase
{
    private OAuthTokenRepository&MockObject $repository;
    private OauthProviderConfigRepository&MockObject $providerConfig;
    private ClientInterface&MockObject $httpClient;
    private RequestFactoryInterface&MockObject $requestFactory;
    private StreamFactoryInterface&MockObject $streamFactory;
    private DefaultOAuthTokenService $service;

    protected function setUp(): void
    {
        $this->repository = $this->createMock(OAuthTokenRepository::class);
        $this->providerConfig = $this->createMock(OauthProviderConfigRepository::class);
        $this->httpClient = $this->createMock(ClientInterface::class);
        $this->requestFactory = $this->createMock(RequestFactoryInterface::class);
        $this->streamFactory = $this->createMock(StreamFactoryInterface::class);

        $this->service = new DefaultOAuthTokenService(
            repository: $this->repository,
            providerConfig: $this->providerConfig,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
        );
    }

    public function testImplementsInterface(): void
    {
        self::assertInstanceOf(OAuthTokenService::class, $this->service);
    }

    public function testGetAccessTokenReturnsTokenWhenNotExpired(): void
    {
        $tokens = new TokenSet('access-xyz', 'Bearer', 3600, null, null, null, time());

        $this->repository->method('load')
            ->with('user1', 'google')
            ->willReturn($tokens);

        self::assertSame('access-xyz', $this->service->getAccessToken('user1', 'google'));
    }

    public function testGetAccessTokenReturnsExpiredTokenWhenNoRefreshToken(): void
    {
        $tokens = new TokenSet('access-xyz', 'Bearer', 3600, null, null, null, time() - 7200);

        $this->repository->method('load')
            ->with('user1', 'google')
            ->willReturn($tokens);

        self::assertSame('access-xyz', $this->service->getAccessToken('user1', 'google'));
    }

    public function testGetAccessTokenThrowsWhenNoTokens(): void
    {
        $this->repository->method('load')
            ->willThrowException(new OAuthTokenNotFoundException('not found'));

        $this->expectException(OAuthTokenNotFoundException::class);
        $this->service->getAccessToken('user1', 'google');
    }

    public function testStoreDelegatesToRepository(): void
    {
        $tokens = new TokenSet('access-xyz', 'Bearer');

        $this->repository->expects(self::once())
            ->method('save')
            ->with('user1', 'google', $tokens);

        $this->service->store('user1', 'google', $tokens);
    }

    public function testHasTokensDelegatesToRepository(): void
    {
        $this->repository->method('exists')
            ->with('user1', 'google')
            ->willReturn(true);

        self::assertTrue($this->service->hasTokens('user1', 'google'));
    }

    public function testRemoveDelegatesToRepository(): void
    {
        $this->repository->expects(self::once())
            ->method('delete')
            ->with('user1', 'google');

        $this->service->remove('user1', 'google');
    }

    public function testGetTokenSetDelegatesToRepository(): void
    {
        $tokens = new TokenSet('access-xyz', 'Bearer', 3600, 'refresh-abc');

        $this->repository->method('load')
            ->with('user1', 'google')
            ->willReturn($tokens);

        $result = $this->service->getTokenSet('user1', 'google');
        self::assertSame('access-xyz', $result->accessToken);
        self::assertSame('refresh-abc', $result->refreshToken);
    }

    public function testGetAccessTokenReturnsNonExpiredTokenWithoutRefreshAttempt(): void
    {
        $tokens = new TokenSet('valid-token', 'Bearer', 3600, 'refresh-abc', null, null, time());

        $this->repository->method('load')
            ->willReturn($tokens);

        $this->providerConfig->expects(self::never())->method('get');

        self::assertSame('valid-token', $this->service->getAccessToken('user1', 'google'));
    }

    public function testGetAccessTokenThrowsWhenProviderConfigNotFound(): void
    {
        $tokens = new TokenSet('expired', 'Bearer', 3600, 'refresh-abc', null, null, time() - 7200);

        $this->repository->method('load')->willReturn($tokens);
        $this->providerConfig->method('get')
            ->willThrowException(new \RuntimeException('not found'));

        $this->expectException(OAuthTokenRefreshException::class);
        $this->expectExceptionMessage("Cannot refresh: provider 'google' not found");
        $this->service->getAccessToken('user1', 'google');
    }
}
