<?php

declare(strict_types=1);

/**
 * Admin Dashboard Controller
 *
 * Presents admin menu items as a responsive/floating card grid under the
 * desktop topbar. Accessible to global admins and users with at least one
 * horde:administration:* permission.
 *
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

use Horde\Core\Horde;
use Horde\Core\PageOutput\AssetCollector;
use Horde\Core\PageOutput\PageComposer;
use Horde\Core\PageOutput\PageMeta;
use Horde\Core\PageOutput\ViewMode;
use Horde\Core\PageOutput\ViewModeConfigurator;
use Horde\Core\Service\PermissionService;
use Horde\Core\Topbar\TopbarBuilder;
use Horde\Core\Topbar\TopbarRenderer;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde_Registry;
use Horde_View;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Exception;

class AdminDashboardController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;

    public function __construct(
        private readonly AssetCollector $assetCollector,
        private readonly PageComposer $pageComposer,
        private readonly ViewModeConfigurator $configurator,
        private readonly TopbarBuilder $topbarBuilder,
        private readonly TopbarRenderer $topbarRenderer,
        private readonly Horde_Registry $registry,
        private readonly PermissionService $permissions,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if (!$this->registry->isAuthenticated()) {
            $webroot = $this->registry->get('webroot', 'horde');
            return $this->redirectToLogin(
                $webroot . '/auth/login',
                $request->getUri()->getPath()
            );
        }

        $uid = $this->registry->getAuth() ?: '';
        $isAdmin = $this->registry->isAdmin();

        $adminItems = $this->getAccessibleItems($uid, $isAdmin);

        if ($adminItems === []) {
            return $this->htmlResponse('<h1>Forbidden</h1><p>No admin permissions.</p>', 403);
        }

        $view = $this->createView();
        $view->adminItems = $adminItems;

        $html = $this->renderChrome(
            _("Administration"),
            fn() => $view->render('dashboard'),
        );

        return $this->htmlResponse($html);
    }

    /**
     * @return array<int, array{name: string, url: string, icon: string, iconUrl: string, cssClass: string}>
     */
    private function getAccessibleItems(string $uid, bool $isAdmin): array
    {
        try {
            $adminList = $this->registry->callByPackage('horde', 'admin_list');
        } catch (Exception) {
            return [];
        }

        $themesUri = $this->registry->get('themesuri', 'horde');
        $items = [];
        foreach ($adminList as $method => $val) {
            if (!$this->hasAccess($method, $isAdmin, $uid)) {
                continue;
            }

            $label = Horde::stripAccessKey($val['name']);
            $url = (string) $this->registry->applicationWebPath($val['link'], 'horde');
            $icon = $val['icon'] ?? '';

            $items[] = [
                'name' => $label,
                'url' => $url,
                'icon' => $icon,
                'iconUrl' => $themesUri . '/default/graphics/admin/' . $icon . '.png',
                'cssClass' => 'horde-admin-' . $icon,
            ];
        }

        return $items;
    }

    private function hasAccess(string $method, bool $isAdmin, string $uid): bool
    {
        if ($isAdmin) {
            return true;
        }

        if ($uid === '') {
            return false;
        }

        $permName = 'horde:administration:' . $method;
        try {
            return $this->permissions->exists($permName)
                && $this->permissions->hasPermission($permName, $uid, ['show']);
        } catch (Exception) {
            return false;
        }
    }

    private function createView(): Horde_View
    {
        $view = new Horde_View([
            'templatePath' => HORDE_TEMPLATES . '/admin',
        ]);
        $view->addHelper('Tag');
        $view->addHelper('Text');

        return $view;
    }

    private function renderChrome(string $title, callable $renderBody): string
    {
        $themesUri = $this->registry->get('themesuri', 'horde');
        $this->assetCollector->addStylesheet($themesUri . '/default/screen.css');
        $this->assetCollector->addStylesheet($themesUri . '/default/admin-dashboard.css');
        $this->configurator->configure($this->assetCollector, ViewMode::BASIC);

        $meta = new PageMeta(title: $title);
        $html = $this->pageComposer->renderHead($meta);

        $topbarData = $this->topbarBuilder->build('horde');
        $html .= $this->topbarRenderer->render($topbarData);

        $html .= $renderBody();

        $html .= $this->pageComposer->renderFoot();

        return $html;
    }
}
