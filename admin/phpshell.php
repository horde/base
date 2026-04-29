<?php

/**
 * PHP Shell.
 *
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Util\Variables;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:phpshell'],
]);

$uriBuilder = $injector->getInstance(UriBuilderInterface::class);
$vars = $injector->getInstance(Variables::class);

$apps_tmp = $registry->listApps();
$apps = [];
foreach ($apps_tmp as $app) {
    // Make sure the app is installed.
    if (!file_exists($registry->get('fileroot', $app))) {
        continue;
    }

    $apps[$app] = $registry->get('name', $app) . ' (' . $app . ')';
}
asort($apps);

$application = $vars->get('app', 'horde');
$command = trim($vars->php);

$title = _("PHP Shell");

$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin',
]);
$view->addHelper('Horde_Core_View_Helper_Help');
$view->addHelper('Text');

$view->action = $uriBuilder->withAppWebroot('horde')->withPart('admin/phpshell.php')->toHordeUrl();
$view->application = $application;
$view->apps = $apps;
$view->command = $command;
$view->title = $title;
$view->session = $session;

if ($command) {
    $session->checkToken($vars->token);
    $pushed = $registry->pushApp($application);

    $part = new Horde_Mime_Part();
    $part->setContents($command);
    $part->setType('application/x-httpd-phps');
    $part->buildMimeIds();

    $pretty = $injector->getInstance('Horde_Core_Factory_MimeViewer')->create($part)->render('inline');
    $view->pretty = $pretty[1]['data'];
    Horde::startBuffer();
    try {
        eval($command);
    } catch (Exception $e) {
        echo $e;
    }
    $view->command_exec = Horde::endBuffer();

    if ($pushed) {
        $registry->popApp();
    }
}

$page_output->addScriptFile('stripe.js', 'horde');
$page_output->header([
    'title' => $title,
]);
require HORDE_TEMPLATES . '/admin/menu.inc';
echo $view->render('phpshell');
$page_output->footer();
