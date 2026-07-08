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

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Util\Variables;

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:perms'],
]);

/* Set up the form variables. */
$uriBuilder = $injector->getInstance(UriBuilderInterface::class);
$vars = $injector->getInstance(Variables::class);
$perms = $injector->getInstance('Horde_Perms');
$corePerms = $injector->getInstance('Horde_Core_Perms');
$perm_id = $vars->get('perm_id');
$category = $vars->get('category');
try {
    $permission = $perms->getPermissionById($perm_id);
} catch (Exception $e) {
    /* If the permission fetched is an error return to permissions list. */
    $notification->push(_("Attempt to delete a non-existent permission."), 'horde.error');
    $uriBuilder->withAppWebroot('horde')->withPart('admin/perms/index.php')->toHordeUrl()->redirect();
}

/* Set up form. */
$ui = $injector
    ->getInstance(\Horde\Core\Factory\PermsUi::class)
    ->create($injector, $perms, $corePerms);
if ($ui instanceof \Horde\Core\Perms\PermsUiInterface) {
    $page_output->addScriptFile('perms-tristate.js', 'horde');
}
$ui->setVars($vars);
$ui->setupDeleteForm($permission);

$info = [];
if ($confirmed = $ui->validateDeleteForm($info)) {
    try {
        $result = $perms->removePermission($permission, true);
        $notification->push(sprintf(_("Successfully deleted \"%s\"."), $corePerms->getTitle($permission->getName())), 'horde.success');
        $uriBuilder->withAppWebroot('horde')->withPart('admin/perms/index.php')->toHordeUrl()->redirect();
    } catch (Exception $e) {
        $notification->push(sprintf(_("Unable to delete \"%s\": %s."), $corePerms->getTitle($permission->getName()), $result->getMessage()), 'horde.error');
    }
} elseif ($confirmed === false) {
    $notification->push(sprintf(_("Permission \"%s\" not deleted."), $corePerms->getTitle($permission->getName())), 'horde.success');
    $uriBuilder->withAppWebroot('horde')->withPart('admin/perms/index.php')->toHordeUrl()->redirect();
}

$page_output->header([
    'title' => _("Permissions Administration"),
]);
require HORDE_TEMPLATES . '/admin/menu.inc';

/* Render the form and tree. */
$ui->renderForm('delete.php');
echo '<br />';
$ui->renderTree($perm_id);

$page_output->footer();
