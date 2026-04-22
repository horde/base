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

use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\Exception\OAuthProviderConfigNotFoundException;
use Horde\Core\Service\OAuthTokenService;
use Horde\Core\Session\HordeSession;
use Horde\Core\Sidebar\SidebarBuilder;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Horde\Service\IdentityLinkService;
use Horde\Horde\Service\UrlGenerator;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Identity\IdentityRole;
use Horde\OAuth\Client\OAuth2Client;
use Horde\OAuth\Client\PkceGenerator;
use Horde\OAuth\Client\ProviderConfig;
use Horde_Notification_Handler;
use Horde_Registry;
use Horde_View;
use Psr\Http\Client\ClientInterface;
use Psr\Http\Message\RequestFactoryInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamFactoryInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class OAuthAccountController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly OAuthTokenService $tokenService,
        private readonly IdentityLinkService $identityLinkService,
        private readonly UrlGenerator $urlGenerator,
        private readonly HordeSession $session,
        private readonly Horde_Notification_Handler $notification,
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly ViewModeConfigurator $configurator,
        private readonly TopbarBuilder $topbarBuilder,
        private readonly TopbarRenderer $topbarRenderer,
        private readonly SidebarBuilder $sidebarBuilder,
        private readonly SidebarRenderer $sidebarRenderer,
        private readonly Horde_Registry $registry,
        private readonly ClientInterface $httpClient,
        private readonly RequestFactoryInterface $requestFactory,
        private readonly StreamFactoryInterface $streamFactory,
    ) {}

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
            fn() => $view->render('list')
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
        } catch (OAuthProviderConfigNotFoundException) {
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

        $this->session->setScoped('horde', 'oauth_flow', [
            'state' => $state,
            'provider_id' => $providerId,
            'pkce_verifier' => $verifier,
        ]);

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
        $params = $request->getQueryParams();

        $loginFlow = $this->session->getScoped('horde', 'oauth_login_flow');
        if (is_array($loginFlow)) {
            return $this->handleLoginCallback($params, $loginFlow);
        }

        return $this->handleAccountLinkCallback($request, $params);
    }

    private function handleLoginCallback(array $params, array $flowData): ResponseInterface
    {
        $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');
        $loginUrl = $webroot . '/auth/login';

        $state = $params['state'] ?? '';
        if (!hash_equals($flowData['state'], $state)) {
            $this->session->removeScoped('horde', 'oauth_login_flow');
            return $this->redirect($loginUrl . '?error=failed');
        }

        if (!empty($params['error'])) {
            $this->session->removeScoped('horde', 'oauth_login_flow');
            return $this->redirect($loginUrl . '?error=failed');
        }

        $code = $params['code'] ?? '';
        if ($code === '') {
            $this->session->removeScoped('horde', 'oauth_login_flow');
            return $this->redirect($loginUrl . '?error=failed');
        }

        $providerId = $flowData['provider_id'];
        $verifier = $flowData['pkce_verifier'];
        $redirectUrl = $flowData['redirect_url'] ?? '';

        try {
            $row = $this->providerConfig->get($providerId);
            $client = $this->buildOAuth2Client($row);
            $tokenSet = $client->exchangeCode($code, $verifier);
            $userinfo = $client->fetchUserinfo($tokenSet->accessToken);

            $externalId = (string) ($userinfo['sub'] ?? $userinfo['id'] ?? '');
            $email = $userinfo['email'] ?? null;
            $displayName = $userinfo['name'] ?? $userinfo['display_name'] ?? $userinfo['login'] ?? null;

            if ($externalId === '') {
                $this->session->removeScoped('horde', 'oauth_login_flow');
                return $this->redirect($loginUrl . '?error=failed');
            }

            $identity = $this->identityLinkService->resolveOrCreate(
                $providerId,
                $externalId,
                $email,
                $displayName,
                IdentityRole::Collaborator,
            );

            $this->identityLinkService->touchLastUsed($providerId, $externalId);

            $this->registry->setAuth($identity->id, []);

            $this->session->removeScoped('horde', 'oauth_login_flow');

            if ($redirectUrl !== '') {
                return $this->redirect($redirectUrl);
            }

            return $this->redirect((string) $this->registry->getServiceLink('portal'));
        } catch (Throwable) {
            $this->session->removeScoped('horde', 'oauth_login_flow');
            return $this->redirect($loginUrl . '?error=failed');
        }
    }

    private function handleAccountLinkCallback(ServerRequestInterface $request, array $params): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');

        $flowData = $this->session->getScoped('horde', 'oauth_flow');
        if (!is_array($flowData)) {
            $this->notification->push(_("No OAuth flow in progress."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $state = $params['state'] ?? '';
        if (!hash_equals($flowData['state'], $state)) {
            $this->session->removeScoped('horde', 'oauth_flow');
            $this->notification->push(_("Invalid state parameter. Please try again."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        if (!empty($params['error'])) {
            $this->session->removeScoped('horde', 'oauth_flow');
            $errorDesc = $params['error_description'] ?? $params['error'];
            $this->notification->push(
                sprintf(_("Authorization failed: %s"), $errorDesc),
                'horde.error'
            );
            return $this->redirect($baseUrl . '/');
        }

        $code = $params['code'] ?? '';
        if ($code === '') {
            $this->session->removeScoped('horde', 'oauth_flow');
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

        $this->session->removeScoped('horde', 'oauth_flow');
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
        $redirectUri = $this->urlGenerator->absoluteUrlFor('SettingsOAuthCallback');

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
        $view = new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/settings/oauthaccount',
        ]);
        $view->addHelper('Tag');
        $view->addHelper('Text');
        return $view;
    }

    private function renderChrome(string $title, callable $renderBody): string
    {
        $themesUri = $this->registry->get('themesuri', 'horde');
        $this->assetCollector->addStylesheet($themesUri . '/default/screen.css');
        $this->assetCollector->addStylesheet($themesUri . '/default/settings.css');
        $this->configurator->configure($this->assetCollector, ViewMode::BASIC);

        $meta = new PageMeta(title: $title);
        $html = $this->pageComposer->renderHead($meta);

        $topbarData = $this->topbarBuilder->build('horde');
        $html .= $this->topbarRenderer->render($topbarData);

        $html .= $renderBody();

        $sidebarData = $this->sidebarBuilder->build('horde');
        $html .= $this->sidebarRenderer->render($sidebarData);

        $html .= '</div>' . "\n";
        $html .= $this->pageComposer->renderFoot();
        return $html;
    }

    private function getBaseUrl(): string
    {
        return rtrim($this->registry->get('webroot', 'horde'), '/') . '/settings/oauth';
    }
}
