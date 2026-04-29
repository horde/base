<?php

/**
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
use Horde\Util\Util;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:cmdshell'],
]);

$uriBuilder = $injector->getInstance(UriBuilderInterface::class);
$title = _("Command Shell");

$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin',
]);
$view->addHelper('Horde_Core_View_Helper_Help');
$view->addHelper('Text');

$view->action = $uriBuilder->withAppWebroot('horde')->withPart('admin/cmdshell.php')->toHordeUrl();
$view->command = trim(Util::getFormData('cmd'));
$view->title = $title;
$view->session = $session;
if ($view->command) {
    $session->checkToken(Util::getPost('token'));
    $cmds = explode("\n", $view->command);
    $out = [];

    foreach ($cmds as $cmd) {
        $cmd = trim($cmd);
        if (strlen($cmd)) {
            $out[] = shell_exec($cmd);
        }
    }

    $view->out = $out;
}

$page_output->header([
    'title' => $title,
]);
require HORDE_TEMPLATES . '/admin/menu.inc';
echo $view->render('cmdshell');
$page_output->footer();
