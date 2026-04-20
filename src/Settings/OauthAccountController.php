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

namespace Horde\Horde\Settings;

use Horde\Core\Service\OauthProviderConfigRepository;
use Horde\Core\Service\Exception\OauthProviderConfigNotFoundException;
use Horde\Core\Service\OAuthTokenService;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Oauth\Client\OAuth2Client;
use Horde\Oauth\Client\PkceGenerator;
use Horde\Oauth\Client\ProviderConfig;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_View;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class OauthAccountController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly OauthProviderConfigRepository $providerConfig,
        private readonly OAuthTokenService $tokenService,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route') ?? [];
        $action = $route['action'] ?? 'list';
        $providerId = $route['providerId'] ?? null;

        return match ($action) {
            'list' => $this->listProviders($request),
            'connect' => $this->connect($request, $providerId),
            'callback' => $this->callback($request),
            'disconnect' => $this->disconnect($request, $providerId),
            default => $this->listProviders($request),
        };
    }

    private function listProviders(ServerRequestInterface $request): ResponseInterface
    {
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        $providers = $this->providerConfig->listEnabled();

        $items = [];
        foreach ($providers as $config) {
            $items[] = [
                'config' => $config,
                'connected' => $this->tokenService->hasTokens($userId, $config['provider_id']),
            ];
        }

        $view = $this->createView();
        $view->providers = $items;
        $view->baseUrl = $this->getBaseUrl();

        $html = $this->renderChrome(
            _("Connected Accounts"),
            fn () => print $view->render('list')
        );

        return $this->htmlResponse($html);
    }

    private function connect(ServerRequestInterface $request, ?string $providerId): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();

        if ($providerId === null) {
            return $this->redirect($baseUrl . '/');
        }

        try {
            $row = $this->providerConfig->get($providerId);
        } catch (OauthProviderConfigNotFoundException) {
            $this->notification->push(sprintf(_("Provider '%s' not found."), $providerId), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        if (empty($row['enabled'])) {
            $this->notification->push(sprintf(_("Provider '%s' is not enabled."), $providerId), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $verifier = PkceGenerator::generateVerifier();
        $challenge = PkceGenerator::computeChallenge($verifier);
        $state = bin2hex(random_bytes(32));

        $_SESSION['horde']['oauth_flow'] = [
            'state' => $state,
            'provider_id' => $providerId,
            'pkce_verifier' => $verifier,
        ];

        $client = $this->buildOAuth2Client($row);
        $scopes = !empty($row['default_scopes']) ? explode(' ', $row['default_scopes']) : [];

        $authUrl = $client->getAuthorizationUrl(
            scopes: $scopes,
            state: $state,
            codeChallenge: $challenge,
            codeChallengeMethod: 'S256',
        );

        return $this->redirect($authUrl);
    }

    private function callback(ServerRequestInterface $request): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        $params = $request->getQueryParams();

        $flowData = $_SESSION['horde']['oauth_flow'] ?? null;
        if ($flowData === null) {
            $this->notification->push(_("No OAuth flow in progress."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $state = $params['state'] ?? '';
        if (!hash_equals($flowData['state'], $state)) {
            unset($_SESSION['horde']['oauth_flow']);
            $this->notification->push(_("Invalid state parameter. Please try again."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        if (!empty($params['error'])) {
            unset($_SESSION['horde']['oauth_flow']);
            $errorDesc = $params['error_description'] ?? $params['error'];
            $this->notification->push(
                sprintf(_("Authorization failed: %s"), $errorDesc),
                'horde.error'
            );
            return $this->redirect($baseUrl . '/');
        }

        $code = $params['code'] ?? '';
        if ($code === '') {
            unset($_SESSION['horde']['oauth_flow']);
            $this->notification->push(_("No authorization code received."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $providerId = $flowData['provider_id'];
        $verifier = $flowData['pkce_verifier'];

        try {
            $row = $this->providerConfig->get($providerId);
            $client = $this->buildOAuth2Client($row);
            $tokenSet = $client->exchangeCode($code, $verifier);
            $this->tokenService->store($userId, $providerId, $tokenSet);

            $this->notification->push(
                sprintf(_("Successfully connected to %s."), $row['name'] ?? $providerId),
                'horde.success'
            );
        } catch (Throwable $e) {
            $this->notification->push(
                sprintf(_("Failed to complete authorization: %s"), $e->getMessage()),
                'horde.error'
            );
        }

        unset($_SESSION['horde']['oauth_flow']);
        return $this->redirect($baseUrl . '/');
    }

    private function disconnect(ServerRequestInterface $request, ?string $providerId): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');

        if ($providerId === null) {
            return $this->redirect($baseUrl . '/');
        }

        $this->tokenService->remove($userId, $providerId);
        $this->notification->push(
            sprintf(_("Disconnected from %s."), $providerId),
            'horde.success'
        );

        return $this->redirect($baseUrl . '/');
    }

    private function buildOAuth2Client(array $row): OAuth2Client
    {
        $providerConfig = ProviderConfig::fromArray($row);
        $redirectUri = $row['redirect_uri'] ?? $this->getBaseUrl() . '/callback';

        return new OAuth2Client(
            provider: $providerConfig,
            clientId: $row['client_id'] ?? '',
            clientSecret: $row['client_secret'] ?? null,
            redirectUri: $redirectUri,
            httpClient: $this->httpClient,
            requestFactory: $this->requestFactory,
            streamFactory: $this->streamFactory,
        );
    }

    private function createView(): Horde_View
    {
        return new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/settings/oauthaccount',
        ]);
    }

    private function renderChrome(string $title, callable $renderBody): string
    {
        ob_start();
        $this->pageOutput->header(['title' => $title]);
        $this->notification->notify(['listeners' => 'status']);
        $renderBody();
        $this->pageOutput->footer();

        return ob_get_clean();
    }

    private function getBaseUrl(): string
    {
        return rtrim($this->registry->get('webroot', 'horde'), '/') . '/settings/oauth';
    }
}
