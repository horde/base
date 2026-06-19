<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Mike Cochrane <mike@graftonhall.co.nz>
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Util\Util;

require_once __DIR__ . '/../../lib/Application.php';
Horde_Registry::appInit('horde');

$blocks = $injector->getInstance('Horde_Core_Factory_BlockCollection')->create();
$layout = $blocks->getLayoutManager();

$action = (string) Util::getFormData('action');
$row = (int) Util::getFormData('row');
$col = (int) Util::getFormData('col');

$layoutLocked = $prefs->isLocked('portal_layout');
$blockedAction = ($action === 'save')
    || ($action === 'save-resume')
    || str_starts_with($action, 'move/')
    || str_starts_with($action, 'expand/')
    || str_starts_with($action, 'shrink/');

if ($layoutLocked && $blockedAction) {
    $notification->push(
        _('The portal layout has been locked by the administrator and cannot be changed.'),
        'horde.error'
    );
} else {
    try {
        $layout->handle($action, $row, $col);
    } catch (Horde_Exception $e) {
        Horde::log($e, Horde_Log::ERR);
        $notification->push($e, 'horde.error');
    }
}

if ($layout->updated()) {
    if ($prefs->isLocked('portal_layout')) {
        $notification->push(
            _('The portal layout has been locked by the administrator and cannot be changed.'),
            'horde.error'
        );
    } elseif (!$prefs->setValue('portal_layout', $layout->serialize())) {
        $notification->push(
            _('Failed to save the portal layout.'),
            'horde.error'
        );
    } else {
        try {
            $prefs->store(true);
            $notification->push(_('Portal layout saved.'), 'horde.message');
            if ($url = Horde::verifySignedUrl(Util::getFormData('url'))) {
                $url = new Horde_Url($url);
                $url->unique()->redirect();
            }
        } catch (Horde_Exception $e) {
            Horde::log($e, Horde_Log::ERR);
            $notification->push($e, 'horde.error');
        }
    }
}

$page_output->sidebar = false;

$page_output->header([
    'title' => _("My Portal Layout"),
]);
$notification->notify(['listeners' => 'status']);
require HORDE_TEMPLATES . '/portal/edit.inc';
$page_output->footer();
