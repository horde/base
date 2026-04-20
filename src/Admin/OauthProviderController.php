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

use Horde\Core\Service\OauthProviderConfigRepository;
use Horde\Core\Service\Exception\OauthProviderConfigNotFoundException;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Oauth\Client\ProviderDiscovery;
use Horde_Notification_Handler;
use Horde_PageOutput;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OauthProviderController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly OauthProviderConfigRepository $repository,
        private readonly Horde_Notification_Handler $notification,
        private readonly Horde_PageOutput $pageOutput,
        private readonly Horde_Registry $registry,
        private readonly ?ProviderDiscovery $discovery = null,
    ) {
    }

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

        $view = $this->createView();
        $view->providers = $providers;
        $view->baseUrl = $this->getBaseUrl();

        $html = $this->renderChrome(
            _("OAuth Providers"),
            fn () => print $view->render('list')
        );

        return $this->htmlResponse($html);
    }

    private function createProvider(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody() ?? [];
        $baseUrl = $this->getBaseUrl();

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
            } catch (\Throwable $e) {
                $this->notification->push(
                    sprintf(_("Auto-discovery failed: %s. You can configure endpoints manually."), $e->getMessage()),
                    'horde.warning'
                );
            }
        }

        $this->repository->save($providerId, $data);
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
        } catch (OauthProviderConfigNotFoundException) {
            $this->notification->push(sprintf(_("Provider '%s' not found."), $providerId), 'horde.error');
            return $this->redirect($baseUrl . '/');
        }

        $view = $this->createView();
        $view->provider = $provider;
        $view->baseUrl = $baseUrl;

        $template = $provider['type'] === 'service_app' ? 'edit-service-app' : 'edit-oauth2';

        $html = $this->renderChrome(
            sprintf(_("Edit Provider: %s"), $provider['name']),
            fn () => print $view->render($template)
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
        } catch (OauthProviderConfigNotFoundException) {
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
        } catch (\Throwable $e) {
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
            'default_scopes', 'redirect_uri',
            'app_identifier', 'installation_id',
        ];

        $data = [];
        foreach ($fields as $field) {
            if (array_key_exists($field, $body)) {
                $data[$field] = $body[$field];
            }
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

        return $data;
    }

    private function createView(): Horde_View
    {
        return new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/admin/oauthprovider',
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
        return rtrim($this->registry->get('webroot', 'horde'), '/') . '/admin/authentication/provider';
    }
}
