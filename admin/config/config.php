<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Util\Util;

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:configuration'],
]);

$uriBuilder = $injector->getInstance(UriBuilderInterface::class);

if (!Util::extensionExists('domxml')
    && !Util::extensionExists('dom')) {
    throw new Horde_Exception('You need the domxml or dom PHP extension to use the configuration tool.');
}

$vars = $injector->getInstance('Horde_Variables');

$app = $vars->app;
$appname = $registry->get('name', $app);
$title = sprintf(_("%s Configuration"), $appname);

if (empty($app) || !in_array($app, $registry->listAllApps())) {
    $notification->push(_("Invalid application."), 'horde.error');
    $uriBuilder->withAppWebroot('horde')->withPart('admin/config/index.php')->toHordeUrl()->redirect();
}
$appConfigFormClass = sprintf("Horde\%s\Config\Form", ucfirst($app));
if (class_exists($appConfigFormClass)) {
    $form = new $appConfigFormClass($vars);
} else {
    $form = new Horde_Config_Form($vars, $app);
}
$form->setButtons(sprintf(_("Generate %s Configuration"), $appname));
if (defined(HORDE_CONFIG_BASE)) {
    $path = HORDE_CONFIG_BASE . DIRECTORY_SEPARATOR . $app;
} else {
    $path = $registry->get('fileroot', $app) . '/config';
}

if (file_exists($path . '/config/conf.bak.php')) {
    $form->appendButtons(_("Revert Configuration"));
}

$php = '';
$configFile = $path . '/conf.php';
if (is_link($configFile)) {
    $configFile = readlink($configFile);
}
if ($vars->submitbutton == _("Revert Configuration")) {
    if (@copy($path . '/conf.bak.php', $configFile)) {
        $notification->push(_("Successfully reverted configuration. Reload to see changes."), 'horde.success');
        @unlink($path . '/conf.bak.php');
    } else {
        $notification->push(_("Could not revert configuration."), 'horde.error');
    }
} elseif ($form->validate($vars)) {
    $config = new Horde_Config($app);
    if ($config->writePHPConfig($vars, $php)) {
        $uriBuilder->withAppWebroot('horde')->withPart('admin/config/index.php')->toHordeUrl()->redirect();
    } else {
        $configIndexUrl = $uriBuilder->withAppWebroot('horde')->withPart('admin/config/index.php')->toHordeUrl();
        $notification->push(sprintf(_("Could not save the configuration file %s. You can either use one of the options to save the code back on %s or copy manually the code below to %s."), Util::realPath($configFile), $configIndexUrl->copy()->setAnchor('update')->link(['title' => _("Configuration")]) . _("Configuration") . '</a>', Util::realPath($configFile)), 'horde.warning', ['content.raw', 'sticky']);
    }
} elseif ($form->isSubmitted()) {
    $notification->push(_("There was an error in the configuration form. Perhaps you left out a required field."), 'horde.error');
}

$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin/config',
]);
$view->addHelper('Text');

$view->php = $php;

/* Create the link for the diff popup only if stored in session. */
if ($session->exists('horde', 'config/' . $app)) {
    $url = $uriBuilder->withAppWebroot('horde')->withPart('admin/config/diff.php')
        ->withQueryParams(['app' => $app])->toHordeUrl();
    $view->diff_popup = Horde::link('#', '', '', '', Horde::popupJs($url, ['height' => 480, 'width' => 640, 'urlencode' => true]) . 'return false;') . _("show differences") . '</a>';
}

Horde::startBuffer();
require HORDE_TEMPLATES . '/admin/menu.inc';
$menu_output = Horde::endBuffer();

/* Render the configuration form. */
$renderer = $form->getRenderer();
$renderer->setAttrColumnWidth('50%');

/* Buffer the form template */
Horde::startBuffer();
$form->renderActive($renderer, $vars, $uriBuilder->withAppWebroot('horde')->withPart('admin/config/config.php')->toHordeUrl(), 'post');
$view->form = Horde::endBuffer();

/* Send headers */
$page_output->header([
    'title' => $title,
]);

/* Output page */
echo $menu_output . $view->render('config');
$page_output->footer();
