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
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde\Admin;

use Horde\Core\Api\ApiRegistry;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Sidebar\AdminSidebarPanel;
use Horde\Core\Sidebar\SidebarRenderer;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Rpc\Dispatch\ApiCallContext;
use Horde\Rpc\Dispatch\MethodDescriptor;
use Horde_Injector;
use Horde\Injector\Injector;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Exception;

class ApiRegistryController implements RequestHandlerInterface
{
    use HtmlResponseTrait;

    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly ViewModeConfigurator $configurator,
        private readonly TopbarBuilder $topbarBuilder,
        private readonly TopbarRenderer $topbarRenderer,
        private readonly AdminSidebarPanel $adminPanel,
        private readonly SidebarRenderer $sidebarRenderer,
        private readonly Horde_Registry $registry,
        private readonly Horde_Injector|Injector $injector,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $view = $this->createView();
        $view->legacyApis = $this->collectLegacyApis();
        $view->modernInterfaces = $this->collectModernInterfaces();

        $html = $this->renderChrome(
            _("API Registry"),
            fn() => $view->render('list'),
            $request->getUri()->getPath(),
        );

        return $this->htmlResponse($html);
    }

    private function collectLegacyApis(): array
    {
        $apis = [];
        foreach ($this->registry->listAPIs() as $api) {
            $app = $this->registry->hasInterface($api);
            $methods = [];
            try {
                foreach ($this->registry->listMethods($api) as $fqMethod) {
                    $parts = explode('/', $fqMethod, 2);
                    $methods[] = $parts[1] ?? $fqMethod;
                }
            } catch (Exception $e) {
                $methods = [];
            }
            $apis[] = [
                'name' => $api,
                'app' => $app ?: '',
                'methods' => $methods,
            ];
        }
        usort($apis, fn(array $a, array $b) => strcmp($a['name'], $b['name']));

        return $apis;
    }

    private function collectModernInterfaces(): array
    {
        try {
            $apiRegistry = $this->injector->getInstance(ApiRegistry::class);
        } catch (Exception $e) {
            return [];
        }

        $context = new ApiCallContext(['permissions' => ['admin']]);

        $interfaces = [];
        foreach ($apiRegistry->getInterfaces() as $iface) {
            $provider = $apiRegistry->getProviderForInterface($iface);
            $methods = [];
            foreach ($provider->listMethods($context) as $descriptor) {
                $methods[] = [
                    'name' => $descriptor->name,
                    'description' => $descriptor->description,
                    'returnType' => $descriptor->returnType,
                    'permissions' => $descriptor->permissions,
                ];
            }
            $interfaces[] = [
                'name' => $iface,
                'provider' => get_class($provider),
                'methods' => $methods,
            ];
        }
        usort($interfaces, fn(array $a, array $b) => strcmp($a['name'], $b['name']));

        return $interfaces;
    }

    private function createView(): Horde_View
    {
        $view = new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/admin/apiregistry',
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
