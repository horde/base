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

namespace Horde\Horde\Service;

use Horde\Core\Service\Exception\OAuthTokenRefreshException;
use Horde\Core\Service\OauthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Oauth\Client\TokenRefresher;
use Horde\Oauth\Client\TokenSet;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Throwable;

/**
 * Default OAuth token service with transparent refresh.
 *
 * When getAccessToken() finds an expired token with a refresh_token,
 * it refreshes transparently via the provider's token endpoint and
 * stores the updated tokens before returning.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class DefaultOAuthTokenService implements OAuthTokenService
{
    public function __construct(
        private readonly OAuthTokenRepository $repository,
        private readonly OauthProviderConfigRepository $providerConfig,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function getAccessToken(string $userId, string $providerId): string
    {
        $tokenSet = $this->repository->load($userId, $providerId);

        if (!$tokenSet->isExpired()) {
            return $tokenSet->accessToken;
        }

        if ($tokenSet->refreshToken === null) {
            return $tokenSet->accessToken;
        }

        $refreshed = $this->refresh($providerId, $tokenSet);
        $this->repository->save($userId, $providerId, $refreshed);

        return $refreshed->accessToken;
    }

    public function store(string $userId, string $providerId, TokenSet $tokens): void
    {
        $this->repository->save($userId, $providerId, $tokens);
    }

    public function hasTokens(string $userId, string $providerId): bool
    {
        return $this->repository->exists($userId, $providerId);
    }

    public function remove(string $userId, string $providerId): void
    {
        $this->repository->delete($userId, $providerId);
    }

    public function getTokenSet(string $userId, string $providerId): TokenSet
    {
        return $this->repository->load($userId, $providerId);
    }

    private function refresh(string $providerId, TokenSet $tokenSet): TokenSet
    {
        try {
            $config = $this->providerConfig->get($providerId);
        } catch (Throwable $e) {
            throw new OAuthTokenRefreshException(
                "Cannot refresh: provider '{$providerId}' not found",
                0,
                $e
            );
        }

        $refresher = new TokenRefresher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            tokenEndpoint: $config['token_endpoint'] ?? '',
            clientId: $config['client_id'] ?? '',
            clientSecret: $config['client_secret'] ?? null,
        );

        try {
            $refreshed = $refresher->refresh($tokenSet->refreshToken);
        } catch (Throwable $e) {
            throw new OAuthTokenRefreshException(
                "Token refresh failed for provider '{$providerId}': " . $e->getMessage(),
                0,
                $e
            );
        }

        // Preserve the old refresh token if the provider didn't rotate it
        if ($refreshed->refreshToken === null && $tokenSet->refreshToken !== null) {
            $refreshed = new TokenSet(
                accessToken: $refreshed->accessToken,
                tokenType: $refreshed->tokenType,
                expiresIn: $refreshed->expiresIn,
                refreshToken: $tokenSet->refreshToken,
                scope: $refreshed->scope ?? $tokenSet->scope,
                idToken: $refreshed->idToken,
                receivedAt: $refreshed->receivedAt,
            );
        }

        return $refreshed;
    }
}
