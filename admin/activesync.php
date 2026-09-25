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
 * @author   Torben Dannhauer
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\ActiveSync\Ops\SnapshotCriteria;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Exception\HordeThrowable;
use Horde\Horde\HordeConfig;
use Horde\Util\HordeString;
use Horde\Util\Util;
use Psr\Log\LoggerInterface;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:activesync'],
]);
$config = $injector->get(HordeConfig::class);

// Build diagnostic status (always shown)
$status = [];
$status['package'] = class_exists('Horde_ActiveSync_State_Sql')
    || class_exists('Horde_ActiveSync_State_Mongo');
$status['enabled'] = !empty($config->get('activesync.enabled'));
$status['storage'] = $config->get('activesync.storage') ?? '';
$status['database'] = null;
if ($status['enabled'] && HordeString::lower($status['storage'] ?: 'sql') === 'sql') {
    try {
        $injector->get('Horde_Core_Factory_Db')
            ->create('horde', 'activesync');
        $status['database'] = true;
    } catch (Throwable $e) {
        $status['database'] = $e->getMessage();
    }
}

// Try to get the ActiveSync state driver
$state = null;
try {
    $state = $injector->get('Horde_ActiveSyncState');
} catch (HordeThrowable $e) {
}

// Process device actions if ActiveSync is operational
if ($state) {
    $logger = $injector->get(LoggerInterface::class);
    // ActiveSync's compatibility wrapper does not yet accept PSR-3 loggers.
    $activeSyncLogger = $injector->get('Horde_Log_Logger');
    $state->setLogger($activeSyncLogger);

    if ($actionID = Util::getPost('actionID')) {
        $deviceIDRaw = Util::getPost('deviceID');
        $device_desc = explode(':', $deviceIDRaw, 2);
        $deviceID = $device_desc[0];
        $actionUser = Util::getPost('uid') ?: ($device_desc[1] ?? null);

        switch ($actionID) {
            case 'wipe':
                $state->setDeviceRWStatus($deviceID, Horde_ActiveSync::RWSTATUS_PENDING);
                $GLOBALS['notification']->push(_("A full device wipe has been requested. The device will be reset on the next synchronization."), 'horde.success');
                break;

            case 'accountwipe':
                if (empty($actionUser)) {
                    $GLOBALS['notification']->push(_("Unable to determine which account to wipe."), 'horde.error');
                    break;
                }
                $device = $state->loadDeviceInfo($deviceID, $actionUser);
                if (!Horde_ActiveSync::deviceSupportsAccountOnlyWipe($device->version ?? null)) {
                    $GLOBALS['notification']->push(_("Account-only wipe requires a device with EAS 16.1 or newer."), 'horde.error');
                    break;
                }
                $state->setAccountOnlyRWStatus(
                    $deviceID,
                    $actionUser,
                    Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING
                );
                $GLOBALS['notification']->push(_("An account-only wipe has been requested. The account will be removed from the device on the next synchronization."), 'horde.success');
                break;

            case 'cancelwipe':
                if (!empty($actionUser)) {
                    $state->setAccountOnlyRWStatus(
                        $deviceID,
                        $actionUser,
                        Horde_ActiveSync::RWSTATUS_OK
                    );
                }
                $state->setDeviceRWStatus($deviceID, Horde_ActiveSync::RWSTATUS_OK);
                $GLOBALS['notification']->push(_("Device wipe successfully canceled."), 'horde.success');
                break;

            case 'delete':
                if (empty($actionUser)) {
                    $GLOBALS['notification']->push(_("Unable to determine which account to delete."), 'horde.error');
                    break;
                }
                $state->removeState(
                    [
                        'devId' => $deviceID,
                        'user' => $actionUser,
                    ]
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
    $view->addHelper('Text');

    $selfurl = Horde::selfUrl();
    $view->reset = $selfurl->copy()->add('reset', 1);
    $devs = [];
    $js = [];
    $rows = [];
    $healthByKey = [];
    $summary = null;
    try {
        // The snapshot and table rendering each load SyncCache per device.
        // This duplicate work is acceptable for the initial health view.
        $snapshot = $injector->get(SnapshotService::class)->fleet(
            new SnapshotCriteria(
                user: Util::getPost('searchBy') === 'username'
                    ? Util::getPost('searchInput')
                    : null,
                limit: 0,
                sort: SnapshotCriteria::SORT_USER
            )
        );
        foreach ($snapshot->devices as $deviceHealth) {
            $key = $deviceHealth->user . ':' . $deviceHealth->deviceId;
            $healthByKey[$key] = $deviceHealth;
        }
        $summary = $snapshot->summary;
    } catch (HordeThrowable $e) {
        $logger->info($e->getMessage(), ['exception' => $e]);
    }

    foreach (array_values($devices) as $device) {
        $dev = $state->loadDeviceInfo($device['device_id'], $device['device_user']);
        try {
            $dev = $injector->get('Horde_Core_Hooks')
                ->callHook('activesync_device_modify', 'horde', [$dev]);
        } catch (Horde_Exception_HookNotSet $e) {
        }
        $syncCache = new Horde_ActiveSync_SyncCache(
            $state,
            $dev->id,
            $dev->user,
            $activeSyncLogger
        );
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
        $rows[] = [
            'device' => $dev,
            'collections' => $collection,
            'health' => $healthByKey[$dev->user . ':' . $dev->id] ?? null,
        ];
    }
    $rows = Horde_ActiveSync_DeviceTable::sortRows($rows, true);
    $view->entries = Horde_ActiveSync_DeviceTable::toEntries($rows, true);
    $view->groupByUser = true;
    $view->devices = $devs;
    $view->collections = array_column($rows, 'collections');
    $view->isAdmin = true;
    $view->summary = $summary;
    $view->hasHealth = $summary !== null;
    $view->timezone = $prefs->getValue('timezone');
    $view->dateFormat = $prefs->getValue('date_format');
    $view->language = $language ?? 'en_US';

    $page_output->addScriptFile('activesyncadmin.js', 'horde');
    $page_output->addInlineJsVars([
        'HordeActiveSyncAdmin.devices' => $js,
    ]);
    if ($summary !== null) {
        echo $view->render('activesync_health');
    }
    echo $view->render('activesync');
}

echo $page_output->footer();
