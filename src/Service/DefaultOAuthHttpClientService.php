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

use Horde\Core\Service\Exception\OAuthInsufficientScopeException;
use Horde\Core\Service\Exception\OAuthTokenNotFoundException;
use Horde\Core\Service\OAuthHttpClientService;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\OAuthTokenService;
use Horde\Core\Service\ScopeCheckResult;
use Horde\Core\Service\WantedScopes;
use Horde\OAuth\Client\AuthenticatedHttpClient;
use Horde\OAuth\Client\ScopeSet;
use Horde\OAuth\Client\TokenRefresher;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\StreamFactoryInterface;

class DefaultOAuthHttpClientService implements OAuthHttpClientService
{
    public function __construct(
        private readonly OAuthTokenService $tokenService,
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function getClient(
        string $userId,
        string $providerId,
        ?WantedScopes $wantedScopes = null,
    ): AuthenticatedHttpClient {
        if ($wantedScopes !== null) {
            $result = $this->tokenService->checkScopes($userId, $providerId, $wantedScopes);

            if ($result === ScopeCheckResult::NoToken) {
                throw new OAuthTokenNotFoundException(
                    "No OAuth tokens for user '{$userId}' / provider '{$providerId}'"
                );
            }

            if ($result === ScopeCheckResult::Insufficient) {
                $tokenSet = $this->tokenService->getTokenSet($userId, $providerId);
                $granted = ScopeSet::fromSpaceSeparated($tokenSet->scope);
                $missing = $wantedScopes->scopes->diff($granted)->toArray();

                throw new OAuthInsufficientScopeException($tokenSet, $missing);
            }
        }

        $tokenSet = $this->tokenService->getTokenSet($userId, $providerId);
        $config = $this->providerConfig->get($providerId);

        $refresher = new TokenRefresher(
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
            tokenEndpoint: $config['token_endpoint'] ?? '',
            clientId: $config['client_id'] ?? '',
            clientSecret: $config['client_secret'] ?? null,
        );

        return new AuthenticatedHttpClient($this->httpClient, $tokenSet, $refresher);
    }
}
