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
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:perms'],
]);

/* Set up the form variables. */
$logger = $injector->getInstance(LoggerInterface::class);
$uriBuilder = $injector->getInstance(UriBuilderInterface::class);
$vars = $injector->getInstance(Variables::class);
$perms = $injector->getInstance('Horde_Perms');
$corePerms = $injector->getInstance('Horde_Core_Perms');
$perm_id = $vars->get('perm_id');

try {
    $permission = $perms->getPermissionById($perm_id);
} catch (Exception $e) {
    $notification->push(_("Invalid parent permission."), 'horde.error');
    $uriBuilder->withAppWebroot('horde')->withPart('admin/perms/index.php')->toHordeUrl()->redirect();
}

/* Set up form. */
$ui = new Horde_Core_Perms_Ui($perms, $corePerms);
$ui->setVars($vars);
$ui->setupAddForm($permission);

if ($info = $ui->validateAddForm($info)) {
    try {
        if ($info['perm_id'] == Horde_Perms::ROOT) {
            $child = $corePerms->newPermission($info['child']);
            $result = $perms->addPermission($child);
        } else {
            $pOb = $perms->getPermissionById($info['perm_id']);
            $name = $pOb->getName() . ':' . str_replace(':', '.', $info['child']);
            $child = $corePerms->newPermission($name);
            $result = $perms->addPermission($child);
        }
        $notification->push(sprintf(_("\"%s\" was added to the permissions system."), $corePerms->getTitle($child->getName())), 'horde.success');
        $uriBuilder->withAppWebroot('horde')->withPart('admin/perms/edit.php')
            ->withQueryParams(['perm_id' => $child->getId()])->toHordeUrl()->redirect();
    } catch (Exception $e) {
        $logger->error($e->getMessage(), ['exception' => $e]);
        $notification->push(sprintf(_("\"%s\" was not created: %s."), $corePerms->getTitle($child->getName()), $e->getMessage()), 'horde.error');
    }
}

$page_output->header([
    'title' => _("Permissions Administration"),
]);
require HORDE_TEMPLATES . '/admin/menu.inc';

/* Render the form and tree. */
$ui->renderForm('addchild.php');
echo '<br />';
$ui->renderTree($perm_id);

$page_output->footer();
