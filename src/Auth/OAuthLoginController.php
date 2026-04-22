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

namespace Horde\Horde\Auth;

use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\Exception\OAuthProviderConfigNotFoundException;
use Horde\Horde\Service\UrlGenerator;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\OAuth\Client\OAuth2Client;
use Horde\OAuth\Client\FileOAuthFlowStore;
use Horde\OAuth\Client\OAuthFlowData;
use Horde\OAuth\Client\OAuthFlowStore;
use Horde\OAuth\Client\PkceGenerator;
use Horde\OAuth\Client\ProviderConfig;
use Horde_Registry;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OAuthLoginController implements RequestHandlerInterface
{
    use RedirectResponseTrait;

    /**
     * TODO: Replace hardcoded FileOAuthFlowStore with injected OAuthFlowStore
     * once DI wiring is in place.
     */
    public function __construct(
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly UrlGenerator $urlGenerator,
        private readonly FileOAuthFlowStore $flowStore = new FileOAuthFlowStore('/tmp', 'horde_oauth_client'),
        private readonly Horde_Registry $registry,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route') ?? [];
        $providerId = $route['providerId'] ?? null;
        $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');
        $loginUrl = $webroot . '/auth/login';

        if ($providerId === null) {
            return $this->redirect($loginUrl . '?error=failed');
        }

        try {
            $row = $this->providerConfig->get($providerId);
        } catch (OAuthProviderConfigNotFoundException) {
            return $this->redirect($loginUrl . '?error=failed');
        }

        if (empty($row['enabled']) || empty($row['client_id'])) {
            return $this->redirect($loginUrl . '?error=failed');
        }

        $body = $request->getParsedBody() ?? [];
        $redirectUrl = is_string($body['url'] ?? null) ? $body['url'] : '';

        $verifier = PkceGenerator::generateVerifier();
        $challenge = PkceGenerator::computeChallenge($verifier);
        $state = bin2hex(random_bytes(32));

        $this->flowStore->save($state, new OAuthFlowData(
            state: $state,
            providerId: $providerId,
            pkceVerifier: $verifier,
            flowType: 'login',
            createdAt: time(),
            redirectUrl: $redirectUrl,
        ));

        $callbackUri = $this->urlGenerator->absoluteUrlFor('SettingsOAuthCallback');
        $providerCfg = ProviderConfig::fromArray($row);

        $client = new OAuth2Client(
            provider: $providerCfg,
            clientId: $row['client_id'],
            clientSecret: $row['client_secret'] ?? null,
            redirectUri: $callbackUri,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
        );

        $scopes = !empty($row['default_scopes']) ? explode(' ', $row['default_scopes']) : [];

        $authUrl = $client->getAuthorizationUrl(
            scopes: $scopes,
            state: $state,
            codeChallenge: $challenge,
            codeChallengeMethod: 'S256',
        );

        return $this->redirect($authUrl);
    }
}
