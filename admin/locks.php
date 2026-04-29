<?php

/**
 * Copyright 2011-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Jan Schneider <jan@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\Uri\UriBuilderInterface;
use Horde\Util\Util;

use function PHP81_BC\strftime;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:locks'],
]);

$horde_lock = $injector->getInstance('Horde_Lock');
$uriBuilder = $injector->getInstance(UriBuilderInterface::class);

if ($lock = Util::getFormData('unlock')) {
    try {
        $horde_lock->clearLock($lock);
        $notification->push(_("The lock has been removed."), 'horde.success');
    } catch (Horde_Lock_Exception $e) {
        $notification->push($e);
    }
}

$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin/locks',
]);
$view->addHelper('Text');

try {
    $format = $prefs->getValue('date_format') . ' ' . $prefs->getValue('time_format');
    $locks = $horde_lock->getLocks();
    $url = $uriBuilder->withAppWebroot('horde')->withPart('admin/locks.php')->toHordeUrl();
    foreach ($locks as &$lock) {
        $lock['unlock_link'] = $url->copy()
            ->add('unlock', $lock['lock_id'])
            ->link()
            . _("Unlock")
            . '</a>';
        if ($appname = $registry->get('name', $lock['lock_scope'])) {
            $lock['scope'] = $appname;
        } else {
            $lock['scope'] = $lock['lock_scope'];
        }
        $lock['start'] = strftime($format, $lock['lock_update_timestamp']);
        $lock['end'] = strftime($format, $lock['lock_expiry_timestamp']);
    }
    $view->locks = $locks;
    $page_output->addScriptFile('tables.js', 'horde');
} catch (Horde_Lock_Exception $e) {
    $view->locks = [];
    $view->error = sprintf(_("Listing locks failed: %s"), $e->getMessage());
}

$page_output->header([
    'title' => _("Locks"),
]);
require HORDE_TEMPLATES . '/admin/menu.inc';
echo $view->render('list');
$page_output->footer();
