<?php

/**
 * Script to show the differences between the currently saved and the newly
 * generated configuration.
 *
 * Copyright 2004-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Util\HordeString;

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:configuration'],
]);

$uriBuilder = $injector->getInstance(UriBuilderInterface::class);
$vars = $injector->getInstance('Horde_Variables');

/* Set up the diff renderer. */
$render_type = $vars->get('render', 'inline');
$class = 'Horde_Text_Diff_Renderer_' . HordeString::ucfirst($render_type);
$renderer = new $class();

/**
 * Private function to render the differences for a specific app.
 */
function _getDiff($app)
{
    global $registry, $renderer, $session;

    /* Read the existing configuration. */
    $current_config = '';
    $path = $registry->get('fileroot', $app) . '/config';
    $current_config = @file_get_contents($path . '/conf.php');

    /* Calculate the differences. */
    $diff = new Horde_Text_Diff(
        'auto',
        [explode("\n", $current_config),
            explode("\n", $session->get('horde', 'config/' . $app))]
    );
    $diff = $renderer->render($diff);

    return empty($diff)
        ? _("No change.")
        : $diff;
}

$diffs = [];
/* Only bother to do anything if there is any config. */
if ($config = $session->get('horde', 'config/')) {
    /* Set up the toggle button for inline/unified. */
    $url = $uriBuilder->withAppWebroot('horde')->withPart('admin/config/diff.php')
        ->withQueryParams(['render' => ($render_type == 'inline') ? 'unified' : 'inline'])->toHordeUrl();

    if ($app = $vars->app) {
        /* Handle a single app request. */
        $toggle_renderer = $url->copy()->setAnchor($app)->link() . (($render_type == 'inline') ? _("unified") : _("inline")) . '</a>';
        $diffs[] = [
            'app'  => $app,
            'diff' => ($render_type == 'inline') ? _getDiff($app) : htmlspecialchars(_getDiff($app)),
            'toggle_renderer' => $toggle_renderer,
        ];
    } else {
        /* List all the apps with generated configuration. */
        ksort($config);
        foreach ($config as $app => $config) {
            $toggle_renderer = $url->copy()->setAnchor($app)->link() . (($render_type == 'inline') ? _("unified") : _("inline")) . '</a>';
            $diffs[] = [
                'app'  => $app,
                'diff' => ($render_type == 'inline') ? _getDiff($app) : htmlspecialchars(_getDiff($app)),
                'toggle_renderer' => $toggle_renderer,
            ];
        }
    }
}

/* Set up the template. */
$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin/config',
]);
$view->diffs = $diffs;

$page_output->topbar = $page_output->sidebar = false;

$page_output->header([
    'title' => _("Configuration Differences"),
]);
echo $view->render('diff');
$page_output->footer();
