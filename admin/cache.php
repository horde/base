<?php

/**
 * Cache management.
 *
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl LGPL-2
 * @package   Horde
 */

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Util\Variables;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:cache'],
]);

$cache = $injector->getInstance('Horde_Cache');
$uriBuilder = $injector->getInstance(UriBuilderInterface::class);
$vars = $injector->getInstance(Variables::class);

if ($vars->clearcache) {
    try {
        $cache->clear();
        $notification->push(
            _("Cache data cleared. NOTE: This does not indicate that cache data was successfully cleared on the backend, only that no error messages were returned."),
            'cli.success'
        );
    } catch (Horde_Exception $e) {
        $notification->push($e, 'horde.error');
    }
}

if ($vars->purgecss) {
    try {
        $static_dir = $registry->get('staticfs', 'horde');
        $removed = 0;

        foreach (glob($static_dir . '/*.css') as $file) {
            if (unlink($file)) {
                ++$removed;
            }
        }

        $notification->push(
            sprintf(
                _("Purged %d cached CSS file(s). CSS will be regenerated on next page load."),
                $removed
            ),
            'cli.success'
        );
    } catch (Exception $e) {
        $notification->push(
            sprintf(_("Error purging CSS cache: %s"), $e->getMessage()),
            'horde.error'
        );
    }
}

$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin',
]);
$view->addHelper('Text');

$view->action = $uriBuilder->withAppWebroot('horde')->withPart('admin/cache.php')->toHordeUrl();
$view->driver = $injector->getInstance('Horde_Core_Factory_Cache')->getDriverName();

$view->rw = $cache->testReadWrite();

// Get CSS cache info
$view->css_enabled = !empty($conf['cachecss']);
if ($view->css_enabled) {
    $static_dir = $registry->get('staticfs', 'horde');
    $css_files = glob($static_dir . '/*.css');
    if ($css_files === false) {
        $css_files = [];
    }
    $view->css_count = count($css_files);
    $view->css_size = 0;
    foreach ($css_files as $file) {
        $view->css_size += filesize($file);
    }
    $view->static_dir = $static_dir;
}

$page_output->header([
    'title' => _("Cache Administration"),
]);
require HORDE_TEMPLATES . '/admin/menu.inc';
echo $view->render('cache');
$page_output->footer();
