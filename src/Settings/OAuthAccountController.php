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
use Horde\Core\Service\IdentityService;
use Horde\Core\Service\OAuthTokenService;
use Horde\Core\Sidebar\SidebarBuilder;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Horde\Service\AuthLink;
use Horde\Horde\Service\IdentityLinkService;
use Horde\Core\Uri\RouteUrlWriter;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Identity\IdentityRole;
use Horde\OAuth\Client\OAuth2Client;
use Horde\OAuth\Client\OAuthFlowData;
use Horde\OAuth\Client\OAuthFlowStore;
use Horde\OAuth\Client\PkceGenerator;
use Horde\OAuth\Client\ProviderConfig;
use Horde\OAuth\Client\ScopeSet;
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
    // @translator: IdentityRole values used in templates
    // _("principal") _("collaborator")

    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly OAuthTokenService $tokenService,
        private readonly IdentityLinkService $identityLinkService,
        private readonly IdentityService $identityService,
        private readonly RouteUrlWriter $urlWriter,
        private readonly OAuthFlowStore $flowStore,
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

        $identity = $this->identityLinkService->resolveByUsername($userId);
        $linksByProvider = [];
        if ($identity !== null) {
            foreach ($this->identityLinkService->getLinksForIdentity($identity->id) as $link) {
                $linksByProvider[$link->provider] = $link;
            }
        }

        $items = [];
        foreach ($providers as $config) {
            $pid = $config['provider_id'];
            $link = $linksByProvider[$pid] ?? null;
            $items[] = [
                'config' => $config,
                'connected' => $this->tokenService->hasTokens($userId, $pid),
                'bound' => $link !== null,
                'external_name' => $link?->externalDisplayName,
                'external_id' => $link?->externalId,
                'profile_url' => $link?->metadata['profile_url'] ?? null,
            ];
        }

        $superseded = [];
        if ($identity !== null) {
            foreach ($this->identityLinkService->findSupersededBy($identity->id) as $old) {
                $oldLinks = $this->identityLinkService->getLinksForIdentity($old->id);
                $superseded[] = [
                    'identity' => $old,
                    'links' => $oldLinks,
                ];
            }
        }

        $view = $this->createView();
        $view->providers = $items;
        $view->identity = $identity;
        $view->superseded = $superseded;
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

        $queryParams = $request->getQueryParams();
        $extraScopes = trim($queryParams['scopes'] ?? '');
        $returnUrl = trim($queryParams['return_url'] ?? '');
        $requestingApp = trim($queryParams['requesting_app'] ?? '');
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');

        $defaultScopes = !empty($row['default_scopes']) ? explode(' ', $row['default_scopes']) : [];
        $neededScopes = $extraScopes !== ''
            ? ScopeSet::fromSpaceSeparated($extraScopes)
            : new ScopeSet(...$defaultScopes);

        $client = $this->buildOAuth2Client($row);

        if ($extraScopes !== '' && $this->tokenService->hasTokens($userId, $providerId)) {
            $tokenSet = $this->tokenService->getTokenSet($userId, $providerId);
            $currentScopes = ScopeSet::fromSpaceSeparated($tokenSet->scope);

            $result = $client->getIncrementalConsentUrl(
                currentScopes: $currentScopes,
                neededScopes: $neededScopes,
            );

            if (!$result->consentNeeded) {
                $this->notification->push(_("Required scopes are already granted."), 'horde.message');
                return $this->redirect($returnUrl !== '' ? $returnUrl : $baseUrl . '/');
            }

            $scopes = $result->mergedScopes->toArray();
        } else {
            $scopes = $neededScopes->toArray();
        }

        $verifier = PkceGenerator::generateVerifier();
        $challenge = PkceGenerator::computeChallenge($verifier);
        $state = bin2hex(random_bytes(32));

        $this->flowStore->save($state, new OAuthFlowData(
            state: $state,
            providerId: $providerId,
            pkceVerifier: $verifier,
            flowType: 'account_link',
            createdAt: time(),
            redirectUrl: $returnUrl,
            requestingApp: $requestingApp,
        ));

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
        $state = $params['state'] ?? '';
        $flowData = $state !== '' ? $this->flowStore->consume($state) : null;

        if ($flowData === null) {
            $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');
            $this->notification->push(_("No OAuth flow in progress."), 'horde.error');
            return $this->redirect($webroot . '/settings/oauth/');
        }

        if ($flowData->flowType === 'login') {
            return $this->handleLoginCallback($request, $params, $flowData);
        }

        return $this->handleAccountLinkCallback($request, $params, $flowData);
    }

    private function handleLoginCallback(
        ServerRequestInterface $request,
        array $params,
        OAuthFlowData $flowData,
    ): ResponseInterface {
        $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');
        $loginUrl = $webroot . '/auth/login';

        if (!empty($params['error'])) {
            return $this->redirect($loginUrl . '?error=failed');
        }

        $code = $params['code'] ?? '';
        if ($code === '') {
            return $this->redirect($loginUrl . '?error=failed');
        }

        $providerId = $flowData->providerId;
        $verifier = $flowData->pkceVerifier;
        $redirectUrl = $flowData->redirectUrl;

        try {
            $row = $this->providerConfig->get($providerId);
            $client = $this->buildOAuth2Client($row);
            $tokenSet = $client->exchangeCode($code, $verifier);
            $userinfo = $client->fetchUserinfo($tokenSet->accessToken);

            $externalId = (string) ($userinfo['sub'] ?? $userinfo['id'] ?? '');
            $email = $userinfo['email'] ?? null;
            $displayName = $userinfo['name'] ?? $userinfo['display_name'] ?? $userinfo['login'] ?? null;

            // Resolve a local Horde username from the userinfo claims.
            // Priority: preferred_username → uid → login → left part of email.
            // This ensures a human-readable Horde username instead of the
            // opaque identity UUID, regardless of whether a local link exists.
            $preferredUsername = $this->resolveLocalUsername($userinfo);

            if ($externalId === '') {
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

            $localUsername = null;
            foreach ($this->identityLinkService->getLinksForIdentity($identity->id) as $link) {
                if ($link->provider === AuthLink::PROVIDER_LOCAL) {
                    $localUsername = $link->externalId;
                    break;
                }
            }

            // No local link yet: create one from the preferred username so that
            // subsequent logins resolve to a human-readable Horde username
            // instead of the identity UUID.
            if ($localUsername === null && $preferredUsername !== null) {
                $this->identityLinkService->coupleLocalUser($identity->id, $preferredUsername);
                $localUsername = $preferredUsername;
            }

            // Store tokens so IMP/Ingo hooks can retrieve them via OAuthTokenService.
            // handleLoginCallback does not link an account but still needs tokens
            // available for XOAUTH2 authentication in mail clients.
            $authId = $localUsername ?? $identity->id;
            $this->tokenService->store($authId, $providerId, $tokenSet);
            $this->registry->setAuth($authId, []);

            if ($this->identityService->getAll($authId) === []) {
                $this->identityService->add($authId, [
                    'id' => 'Default Identity',
                    'fullname' => $displayName ?? '',
                    'from_addr' => $email ?? '',
                ]);
            }

            // Filter login.php as redirect destination — it loops back to the portal
            if ($redirectUrl !== '' && str_contains($redirectUrl, '/login.php')) {
                $redirectUrl = '';
            }

            if ($redirectUrl !== '') {
                return $this->redirect($redirectUrl);
            }

            return $this->redirect(rtrim($this->registry->get('webroot', 'horde'), '/') . '/index.php');

        } catch (Throwable $e) {
            error_log('OAuthLoginCallback failed: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            return $this->redirect($loginUrl . '?error=failed');
        }
    }

    private function handleAccountLinkCallback(
        ServerRequestInterface $request,
        array $params,
        OAuthFlowData $flowData,
    ): ResponseInterface {
        $baseUrl = $this->getBaseUrl();
        $userId = $request->getAttribute('HORDE_AUTHENTICATED_USER');

        if (!empty($params['error'])) {
            $errorDesc = $params['error_description'] ?? $params['error'];
            $this->notification->push(
                sprintf(_("Authorization failed: %s"), $errorDesc),
                'horde.error'
            );
            return $this->redirect($baseUrl . '/');
        }

        $code = $params['code'] ?? '';
        if ($code === '') {
            $this->notification->push(_("No authorization code received."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $providerId = $flowData->providerId;
        $verifier = $flowData->pkceVerifier;

        try {
            $row = $this->providerConfig->get($providerId);
            $client = $this->buildOAuth2Client($row);
            $tokenSet = $client->exchangeCode($code, $verifier);
            $userinfo = $client->fetchUserinfo($tokenSet->accessToken);

            $externalId = (string) ($userinfo['sub'] ?? $userinfo['id'] ?? '');
            $email = $userinfo['email'] ?? null;
            $displayName = $userinfo['name'] ?? $userinfo['display_name'] ?? $userinfo['login'] ?? null;
            $profileUrl = $userinfo['profile'] ?? $userinfo['url'] ?? $userinfo['html_url'] ?? null;

            $this->tokenService->store($userId, $providerId, $tokenSet);

            $identity = $this->identityLinkService->resolveByUsername($userId);
            if ($identity === null) {
                $identity = $this->identityLinkService->resolveOrCreate(
                    AuthLink::PROVIDER_LOCAL,
                    $userId,
                    null,
                    null,
                    IdentityRole::Principal,
                );
            }

            if ($externalId !== '') {
                $existingLink = $this->identityLinkService->resolveByCredentials($providerId, $externalId);
                if ($existingLink !== null && $existingLink->id !== $identity->id) {
                    $this->identityLinkService->supersede($existingLink->id, $identity->id);
                    $this->identityLinkService->unlinkFromIdentity($providerId, $externalId);
                    $existingLink = null;
                }
                if ($existingLink === null) {
                    $metadata = $profileUrl !== null ? ['profile_url' => $profileUrl] : null;
                    $this->identityLinkService->linkToIdentity(
                        $identity->id,
                        $providerId,
                        $externalId,
                        $email,
                        $displayName,
                        $metadata,
                    );
                }
            }

            $this->notification->push(
                sprintf(_("Successfully connected to %s."), $row['name'] ?? $providerId),
                'horde.success'
            );
        } catch (Throwable $e) {
            error_log("OAuthAccountController::handleAccountLinkCallback FAILED: " . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
            $this->notification->push(
                sprintf(_("Failed to complete authorization: %s"), $e->getMessage()),
                'horde.error'
            );
        }

        if ($flowData->redirectUrl !== '') {
            if (str_contains($flowData->redirectUrl, '/login.php')) {
                $initialPage = $this->registry->getInitialPage('horde');
                return $this->redirect($initialPage ?? (string) $this->registry->getServiceLink('portal'));
            }
        }

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

        $identity = $this->identityLinkService->resolveByUsername($userId);
        if ($identity !== null) {
            foreach ($this->identityLinkService->getLinksForIdentity($identity->id) as $link) {
                if ($link->provider === $providerId) {
                    $this->identityLinkService->unlinkFromIdentity($link->provider, $link->externalId);
                }
            }
        }

        $this->notification->push(
            sprintf(_("Disconnected from %s."), $providerId),
            'horde.success'
        );

        return $this->redirect($baseUrl . '/');
    }

    private function buildOAuth2Client(array $row): OAuth2Client
    {
        $providerConfig = ProviderConfig::fromArray($row);
        $redirectUri = $this->urlWriter->absoluteUrlFor('SettingsOAuthCallback');

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


    /**
     * Resolve a local Horde username from OIDC userinfo claims.
     *
     * Priority:
     *   1. preferred_username  (standard OIDC claim, used by CAS, Keycloak…)
     *   2. uid                 (LDAP-style claim, common in university IdPs)
     *   3. login               (GitHub, GitLab)
     *   4. left part of email  (last resort, strips domain)
     *
     * Returns null if none of the above are available, in which case the
     * caller falls back to the identity UUID.
     */
    private function resolveLocalUsername(array $userinfo): ?string
    {
        foreach (['preferred_username', 'uid', 'login', 'sub', 'id'] as $claim) {
            $value = trim((string) ($userinfo[$claim] ?? ''));
            if ($value !== '') {
                // Strip domain suffix if present (e.g. "jdoe@example.com" → "jdoe")
                return strstr($value, '@', true) ?: $value;
            }
        }

        // Last resort: left part of email
        $email = trim((string) ($userinfo['email'] ?? ''));
        if ($email !== '') {
            $local = strstr($email, '@', true);
            if ($local !== false && $local !== '') {
                return $local;
            }
        }

        return null;
    }
}
