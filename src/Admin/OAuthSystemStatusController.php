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

use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Sidebar\AdminSidebarPanel;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Horde\Service\SqlOAuthProviderConfigRepository;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

class OAuthSystemStatusController implements RequestHandlerInterface
{
    use HtmlResponseTrait;

    public function __construct(
        private readonly OAuthProviderConfigRepository $repository,
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly ViewModeConfigurator $configurator,
        private readonly TopbarBuilder $topbarBuilder,
        private readonly TopbarRenderer $topbarRenderer,
        private readonly AdminSidebarPanel $adminPanel,
        private readonly SidebarRenderer $sidebarRenderer,
        private readonly Horde_Registry $registry,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $webroot = rtrim($this->registry->get('webroot', 'horde'), '/');

        $view = $this->createView();
        $view->health = $this->buildHealthStatus();
        $view->apiBaseUrl = $webroot . '/api/v1/admin/oauth';
        $view->configUrl = $webroot . '/admin/config/config.php?app=horde';
        $view->providerUrl = $webroot . '/admin/authentication/provider/';

        $html = $this->renderChrome(
            _("Authentication System Status"),
            fn() => $view->render('status'),
            $request->getUri()->getPath(),
        );

        return $this->htmlResponse($html);
    }

    private function buildHealthStatus(): array
    {
        $conf = $GLOBALS['conf'] ?? [];
        $configBase = defined('HORDE_CONFIG_BASE') ? HORDE_CONFIG_BASE : '';

        $isSql = $this->repository instanceof SqlOAuthProviderConfigRepository;

        $hasEncryption = false;
        if ($isSql && method_exists($this->repository, 'hasEncryption')) {
            $hasEncryption = $this->repository->hasEncryption();
        }

        $signingKeyPath = $conf['oauth_server']['private_key_file'] ?? '';
        if ($signingKeyPath !== '' && !str_starts_with($signingKeyPath, '/') && $configBase !== '') {
            $signingKeyPath = $configBase . '/' . $signingKeyPath;
        }

        $jwtSecretPath = $conf['auth']['jwt']['secret_file'] ?? '';
        if ($jwtSecretPath !== '' && !str_starts_with($jwtSecretPath, '/') && $configBase !== '') {
            $jwtSecretPath = $configBase . '/' . $jwtSecretPath;
        }

        return [
            'backend' => $isSql ? 'sql' : 'null',
            'encryption' => $hasEncryption,
            'signing_key' => [
                'configured' => $signingKeyPath !== '',
                'path' => $signingKeyPath,
                'exists' => $signingKeyPath !== '' && file_exists($signingKeyPath),
            ],
            'jwt_secret' => [
                'configured' => $jwtSecretPath !== '',
                'path' => $jwtSecretPath,
                'exists' => $jwtSecretPath !== '' && file_exists($jwtSecretPath),
            ],
        ];
    }

    private function createView(): Horde_View
    {
        $view = new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/admin/oauthstatus',
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
}
