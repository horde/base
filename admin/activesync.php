<?php

/**
 * Administrative management of ActiveSync devices.
 *
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Michael J. Rubinsky <mrubinsk@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Util\HordeString;
use Horde\Util\Util;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:activesync'],
]);

// Build diagnostic status (always shown)
$status = [];
$status['package'] = class_exists('Horde_ActiveSync_State_Sql')
    || class_exists('Horde_ActiveSync_State_Mongo');
$status['enabled'] = !empty($conf['activesync']['enabled']);
$status['storage'] = $conf['activesync']['storage'] ?? '';
$status['database'] = null;
if ($status['enabled'] && HordeString::lower($status['storage'] ?: 'sql') === 'sql') {
    try {
        $injector->getInstance('Horde_Core_Factory_Db')
            ->create('horde', 'activesync');
        $status['database'] = true;
    } catch (Throwable $e) {
        $status['database'] = $e->getMessage();
    }
}

// Try to get the ActiveSync state driver
$state = null;
try {
    $state = $injector->getInstance('Horde_ActiveSyncState');
} catch (Horde_Exception $e) {
}

// Process device actions if ActiveSync is operational
if ($state) {
    $state->setLogger($injector->getInstance('Horde_Log_Logger'));

    if ($actionID = Util::getPost('actionID')) {
        $deviceID = Util::getPost('deviceID');

        $device_desc = explode(':', $deviceID);
        $deviceID = $device_desc[0];

        switch ($actionID) {
            case 'wipe':
                $state->setDeviceRWStatus($deviceID, Horde_ActiveSync::RWSTATUS_PENDING);
                $GLOBALS['notification']->push(_("A device wipe has been requested. Device will be wiped on next syncronization attempt."), 'horde.success');
                break;

            case 'accountwipe':
                $device = $state->loadDeviceInfo($deviceID);
                if (!Horde_ActiveSync::deviceSupportsAccountOnlyWipe($device->version ?? null)) {
                    $GLOBALS['notification']->push(_("Account-only wipe is only available for devices that support EAS 16.1 or newer."), 'horde.error');
                    break;
                }
                $state->setDeviceRWStatus($deviceID, Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING);
                $GLOBALS['notification']->push(_("An account-only wipe has been requested. The account will be removed on next synchronization attempt."), 'horde.success');
                break;

            case 'cancelwipe':
                $state->setDeviceRWStatus($deviceID, Horde_ActiveSync::RWSTATUS_OK);
                $GLOBALS['notification']->push(_("Device wipe successfully canceled."), 'horde.success');
                break;

            case 'delete':
                $state->removeState(
                    [
                        'devId' => $deviceID,
                        'user' => Util::getPost('uid')]
                );
                $GLOBALS['notification']->push(_("Device successfully removed."), 'horde.success');
                break;

            case 'reset':
                $state->resetAllPolicyKeys();
                $GLOBALS['notification']->push(_("All policy keys successfully reset."), 'horde.success');
                break;

            case 'block':
                $device = $state->loadDeviceInfo($deviceID);
                $device->blocked = true;
                $device->save(false);
                break;

            case 'unblock':
                $device = $state->loadDeviceInfo($deviceID);
                $device->blocked = false;
                $device->save(false);
                break;
        }
    }
}

// Render page
$page_output->addThemeStylesheet('settings.css');
$page_output->header(['title' => _("ActiveSync Administration")]);
require HORDE_TEMPLATES . '/admin/menu.inc';

// Always render status cards
$statusView = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin',
]);
$statusView->addHelper('Tag');
$statusView->addHelper('Text');
$statusView->status = $status;
$statusView->configUrl = Horde::url('admin/config/config.php')->add('app', 'horde');
echo $statusView->render('activesync_status');

// Render device list if ActiveSync is operational
if ($state) {
    switch (Util::getPost('searchBy')) {
        case 'username':
            $devices = $state->listDevices(Util::getPost('searchInput'));
            break;

        default:
            $devices = $state->listDevices(null, [Util::getPost('searchBy') => Util::getPost('searchInput')]);
    }

    $view = new Horde_View([
        'templatePath' => [HORDE_TEMPLATES . '/admin', HORDE_TEMPLATES . '/activesync']]);
    $view->addHelper('Tag');

    $selfurl = Horde::selfUrl();
    $view->reset = $selfurl->copy()->add('reset', 1);
    $devs = [];
    $js = [];
    $collections = [];
    foreach (array_values($devices) as $device) {
        $dev = $state->loadDeviceInfo($device['device_id'], $device['device_user']);
        try {
            $dev = $GLOBALS['injector']->getInstance('Horde_Core_Hooks')
                ->callHook('activesync_device_modify', 'horde', [$dev]);
        } catch (Horde_Exception_HookNotSet $e) {
        }
        $syncCache = new Horde_ActiveSync_SyncCache($state, $dev->id, $dev->user, $injector->getInstance('Horde_Log_Logger'));
        $dev->hbinterval = $syncCache->hbinterval
            ? $syncCache->hbinterval
            : ($syncCache->wait ? $syncCache->wait * 60 : _("Unavailable"));
        $js[$dev->id . ':' . $dev->user] = [
            'id' => $dev->id,
            'user' => $dev->user,
        ];
        $devs[] = $dev;
        $collection = [];
        foreach ($syncCache->getCollections() as $id => $c) {
            $collection[] = [
                _("Collection id") => $id,
                _("Class") => $c['class'],
                _("Server Id") => $c['serverid'],
                _("Last synckey") => $c['lastsynckey'],
            ];
        }
        $collections[] = $collection;
    }
    $view->devices = $devs;
    $view->collections = $collections;
    $view->isAdmin = true;

    $page_output->addScriptFile('activesyncadmin.js', 'horde');
    $page_output->addInlineJsVars([
        'HordeActiveSyncAdmin.devices' => $js,
    ]);
    echo $view->render('activesync');
}

echo $page_output->footer();
