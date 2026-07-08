<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Util\Util;

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:perms'],
]);

$perm_id = Util::getFormData('perm_id');

$page_output->header([
    'title' => _("Permissions Administration"),
]);
require HORDE_TEMPLATES . '/admin/menu.inc';

$ui = $injector
    ->getInstance(\Horde\Core\Factory\PermsUi::class)
    ->create(
        $injector,
        $injector->getInstance('Horde_Perms'),
        $injector->getInstance('Horde_Core_Perms')
    );
if ($ui instanceof \Horde\Core\Perms\PermsUiInterface) {
    // Modern UI needs the client-side tree collapse / expand behavior.
    $page_output->addScriptFile('perms-tristate.js', 'horde');
}

echo '<h1 class="header">' . Horde_Themes_Image::tag('perms.png') . ' ' . _("Permissions") . '</h1>';
$ui->renderTree($perm_id);

$page_output->footer();
