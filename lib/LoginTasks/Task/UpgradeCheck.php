<?php

/**
 * Copyright 2016-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\Service\VersionCheck\UpdateAvailability;
use Horde\Core\Service\VersionCheck\VersionService;
use Horde\Core\Uri\UriBuilderInterface;

/**
 * Login task to check for Horde upgrades, and then report upgrades to an admin
 * via the notification system.
 *
 * Uses VersionService to compare installed versions against the configured
 * upstream source (Packagist by default). Results are cached by the service
 * so repeated logins within the TTL window do not trigger remote requests.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
class Horde_LoginTasks_Task_UpgradeCheck extends Horde_LoginTasks_Task
{
    /**
     * The interval at which to run the task.
     *
     * @var integer
     */
    public $interval = Horde_LoginTasks::WEEKLY;

    /**
     * Display type.
     *
     * @var integer
     */
    public $display = Horde_LoginTasks::DISPLAY_NONE;

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->active = $GLOBALS['registry']->isAdmin();
    }

    /**
     * Perform all functions for this task.
     */
    public function execute()
    {
        global $injector, $notification;

        try {
            $versionService = $injector->getInstance(VersionService::class);
            $statuses = $versionService->checkAll();
        } catch (\Horde_Exception $e) {
            return;
        }

        $hasUpdate = false;
        foreach ($statuses as $status) {
            if ($status->status === UpdateAvailability::UpdateAvailable) {
                $hasUpdate = true;
                break;
            }
        }

        if (!$hasUpdate) {
            return;
        }

        $uriBuilder = $injector->getInstance(UriBuilderInterface::class);
        $configUrl = (string) $uriBuilder
            ->withAppWebroot('horde')
            ->withPart('admin/config/index.php')
            ->withQueryParams(['check_versions' => 1]);

        $notification->push(
            '<a href="' . htmlspecialchars($configUrl) . '">'
                . _("A newer version of an application or library exists.")
                . '</a>',
            'horde.warning',
            ['content.raw', 'sticky']
        );
    }
}
