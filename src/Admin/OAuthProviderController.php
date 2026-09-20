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

namespace Horde\Horde\Admin;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Core\Config\Vhost;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\Exception\OAuthProviderConfigNotFoundException;
use Horde\Core\Sidebar\AdminSidebarPanel;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Core\Uri\RouteUrlWriter;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\OAuth\Client\ProviderDiscovery;
use Horde_Notification_Handler;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Throwable;

class OAuthProviderController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly OAuthProviderConfigRepository $repository,
        private readonly Horde_Notification_Handler $notification,
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly ViewModeConfigurator $configurator,
        private readonly TopbarBuilder $topbarBuilder,
        private readonly TopbarRenderer $topbarRenderer,
        private readonly AdminSidebarPanel $adminPanel,
        private readonly SidebarRenderer $sidebarRenderer,
        private readonly Horde_Registry $registry,
        private readonly RouteUrlWriter $urlWriter,
        private readonly ?ProviderDiscovery $discovery = null,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route') ?? [];
        $action = $route['action'] ?? 'list';
        $providerId = $route['providerId'] ?? null;

        return match ($action) {
            'list' => $this->listProviders($request),
            'create' => $this->createProvider($request),
            'edit' => $this->editProvider($request, $providerId),
            'update' => $this->updateProvider($request, $providerId),
            'delete' => $this->deleteProvider($request, $providerId),
            default => $this->listProviders($request),
        };
    }

    private function listProviders(ServerRequestInterface $request): ResponseInterface
    {
        $providers = $this->repository->listAll();
        $presets = $this->loadPresets();
        $existingIds = array_column($providers, 'provider_id');
        $availablePresets = array_diff_key($presets, array_flip($existingIds));

        $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');
        $view = $this->createView();
        $view->providers = $providers;
        $view->presets = $availablePresets;
        $view->baseUrl = $this->getBaseUrl();
        $view->statusUrl = $webroot . '/admin/authentication/status/';

        $html = $this->renderChrome(
            _("OAuth Providers"),
            fn() => $view->render('list'),
            $request->getUri()->getPath(),
        );

        return $this->htmlResponse($html);
    }

    private function createProvider(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $baseUrl = $this->getBaseUrl();

        $presetKey = $body['_preset'] ?? '';
        if ($presetKey !== '') {
            return $this->createFromPreset($presetKey, $baseUrl);
        }

        $providerId = trim($body['provider_id'] ?? '');
        $type = $body['type'] ?? '';
        $name = trim($body['name'] ?? '');
        $issuer = trim($body['issuer'] ?? '');

        if ($providerId === '' || $name === '' || !in_array($type, ['oauth2', 'oidc', 'service_app'], true)) {
            $this->notification->push(_("Provider ID, name, and valid type are required."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        if (!preg_match('/^[a-z0-9_-]+$/', $providerId)) {
            $this->notification->push(_("Provider ID must contain only lowercase letters, digits, hyphens, and underscores."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        if ($this->repository->exists($providerId)) {
            $this->notification->push(sprintf(_("Provider '%s' already exists."), $providerId), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $data = [
            'type' => $type,
            'name' => $name,
            'issuer' => $issuer,
            'enabled' => 1,
        ];

        if ($type === 'oidc' && $issuer !== '' && $this->discovery !== null) {
            try {
                $discovered = $this->discovery->discover($issuer);
                $endpoints = $discovered->toArray();
                $data = array_merge($data, $endpoints);
                $this->notification->push(_("Endpoints auto-discovered from issuer."), 'horde.success');
            } catch (Throwable $e) {
                $this->notification->push(
                    sprintf(_("Auto-discovery failed: %s. You can configure endpoints manually."), $e->getMessage()),
                    'horde.warning'
                );
            }
        }

        try {
            $this->repository->save($providerId, $data);
        } catch (Throwable $e) {
            $this->notification->push(
                sprintf(_("Failed to save provider: %s"), $e->getMessage()),
                'horde.error'
            );
            return $this->redirect($baseUrl . '/');
        }

        $this->notification->push(sprintf(_("Provider '%s' created."), $name), 'horde.success');

        return $this->redirect($baseUrl . '/' . $providerId);
    }

    private function editProvider(ServerRequestInterface $request, ?string $providerId): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();

        if ($providerId === null) {
            return $this->redirect($baseUrl . '/');
        }

        try {
            $provider = $this->repository->get($providerId);
        } catch (OAuthProviderConfigNotFoundException) {
            $this->notification->push(sprintf(_("Provider '%s' not found."), $providerId), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $presets = $this->loadPresets();
        $preset = $presets[$providerId] ?? null;

        $view = $this->createView();
        $view->provider = $provider;
        $view->baseUrl = $baseUrl;
        $view->setupNotes = $preset['notes'] ?? '';
        $view->callbackUrl = $this->urlWriter->absoluteUrlFor('SettingsOAuthCallback');

        $template = $provider['type'] === 'service_app' ? 'edit-service-app' : 'edit-oauth2';

        $html = $this->renderChrome(
            sprintf(_("Edit Provider: %s"), $provider['name']),
            fn() => $view->render($template),
            $request->getUri()->getPath(),
        );

        return $this->htmlResponse($html);
    }

    private function updateProvider(ServerRequestInterface $request, ?string $providerId): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();

        if ($providerId === null) {
            return $this->redirect($baseUrl . '/');
        }

        try {
            $existing = $this->repository->get($providerId);
        } catch (OAuthProviderConfigNotFoundException) {
            $this->notification->push(sprintf(_("Provider '%s' not found."), $providerId), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $body = $request->getParsedBody() ?? [];

        if (($body['_action'] ?? '') === 'discover' && $this->discovery !== null) {
            return $this->discoverEndpoints($providerId, $existing, $body);
        }

        $data = $this->extractUpdateData($existing, $body);

        $this->repository->save($providerId, $data);
        $this->notification->push(sprintf(_("Provider '%s' updated."), $existing['name']), 'horde.success');

        return $this->redirect($baseUrl . '/' . $providerId);
    }

    private function deleteProvider(ServerRequestInterface $request, ?string $providerId): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();

        if ($providerId === null) {
            return $this->redirect($baseUrl . '/');
        }

        $this->repository->delete($providerId);
        $this->notification->push(sprintf(_("Provider '%s' deleted."), $providerId), 'horde.success');

        return $this->redirect($baseUrl . '/');
    }

    private function discoverEndpoints(string $providerId, array $existing, array $body): ResponseInterface
    {
        $baseUrl = $this->getBaseUrl();
        $issuer = trim($body['issuer'] ?? $existing['issuer'] ?? '');

        if ($issuer === '') {
            $this->notification->push(_("Issuer URL is required for auto-discovery."), 'horde.error');
            return $this->redirect($baseUrl . '/' . $providerId);
        }

        try {
            $discovered = $this->discovery->discover($issuer);
            $endpoints = $discovered->toArray();
            $endpoints['issuer'] = $issuer;
            $this->repository->save($providerId, $endpoints);
            $this->notification->push(_("Endpoints auto-discovered and saved."), 'horde.success');
        } catch (Throwable $e) {
            $this->notification->push(
                sprintf(_("Auto-discovery failed: %s"), $e->getMessage()),
                'horde.error'
            );
        }

        return $this->redirect($baseUrl . '/' . $providerId);
    }

    private function extractUpdateData(array $existing, array $body): array
    {
        $fields = [
            'name', 'enabled', 'issuer', 'client_id',
            'authorization_endpoint', 'token_endpoint',
            'userinfo_endpoint', 'jwks_uri',
            'revocation_endpoint', 'introspection_endpoint',
            'default_scopes',
            'app_identifier', 'installation_id',
            'logout_type', 'end_session_endpoint', 'post_logout_redirect_uri',
            'backchannel_username_claim', 'xoauth2_use_email', 'xoauth2_domain',
        ];

        $data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $body[$field];
            }
        }

        if (isset($data['xoauth2_use_email'])) {
            $data['xoauth2_use_email'] = (int) ($data['xoauth2_use_email'] ?? 0);
        }

        if ($data['enabled'] ?? null !== null) {
            $data['enabled'] = (int) ($data['enabled'] ?? 1);
        }

        if (!empty($body['_replace_client_secret']) && ($body['client_secret'] ?? '') !== '') {
            $data['client_secret'] = $body['client_secret'];
        }

        if (!empty($body['_replace_private_key']) && ($body['private_key'] ?? '') !== '') {
            $data['private_key'] = $body['private_key'];
        }

        $appearanceFields = ['display_label', 'display_icon', 'display_color'];
        foreach ($appearanceFields as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = trim($body[$field]);
            }
        }

        return $data;
    }

    private function createFromPreset(string $presetKey, string $baseUrl): ResponseInterface
    {
        $presets = $this->loadPresets();

        if (!isset($presets[$presetKey])) {
            $this->notification->push(_("Unknown preset."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        if ($this->repository->exists($presetKey)) {
            $this->notification->push(sprintf(_("Provider '%s' already exists."), $presetKey), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $preset = $presets[$presetKey];
        $display = $preset['display'] ?? [];
        unset($preset['display'], $preset['notes']);

        $data = $preset;
        $data['display_label'] = $display['label'] ?? $preset['name'] ?? '';
        $data['display_icon'] = $display['icon'] ?? '';
        $data['display_color'] = $display['color'] ?? '';
        $data['enabled'] = 0;

        if (($data['type'] ?? '') === 'oidc' && ($data['issuer'] ?? '') !== '' && $this->discovery !== null) {
            try {
                $discovered = $this->discovery->discover($data['issuer']);
                $data = array_merge($data, $discovered->toArray());
                $this->notification->push(_("Endpoints auto-discovered from issuer."), 'horde.success');
            } catch (Throwable) {
                $this->notification->push(
                    _("Auto-discovery unavailable. Preset endpoints will be used as fallback."),
                    'horde.warning'
                );
            }
        }

        try {
            $this->repository->save($presetKey, $data);
        } catch (Throwable $e) {
            $this->notification->push(
                sprintf(_("Failed to save provider: %s"), $e->getMessage()),
                'horde.error'
            );
            return $this->redirect($baseUrl . '/');
        }

        if (!$this->repository->exists($presetKey)) {
            $this->notification->push(_("Provider was not persisted. Check database configuration."), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $this->notification->push(
            sprintf(_("Provider '%s' created from preset. Add your Client ID and Client Secret to enable it."), $data['name']),
            'horde.success'
        );

        return $this->redirect($baseUrl . '/' . $presetKey);
    }

    private function loadPresets(): array
    {
        $vendorBase = dirname($this->registry->get('fileroot', 'horde'));
        $loader = new BackendConfigLoader(HORDE_CONFIG_BASE, $vendorBase, new Vhost());
        $result = $loader->load('horde', 'oauth_presets.php')->toArray();

        return $result;
    }

    private function createView(): Horde_View
    {
        $view = new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/admin/oauthprovider',
        ]);
        $view->addHelper('Tag');
        $view->addHelper('Text');

        return $view;
    }

    private function renderChrome(string $title, callable $renderBody, string $currentUrl): string
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

        $sidebarData = $this->adminPanel->buildSidebarData($currentUrl);
        $html .= $this->sidebarRenderer->render($sidebarData);

        $html .= '</div>' . "\n";
        $html .= $this->pageComposer->renderFoot();
        return $html;
    }

    private function getBaseUrl(): string
    {
        return rtrim($this->registry->get('webroot', 'horde'), '/') . '/admin/authentication/provider';
    }
}
