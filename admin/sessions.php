<?php

/**
 * Sessions information.
 *
 * Copyright 2005-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

use Horde\Core\Session\SessionMetaInterface;
use Horde\SessionHandler\Exception\CapabilityException;
use Horde\SessionHandler\SessionAdministrator;
use Horde\SessionHandler\SessionHandler;

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', [
    'permission' => ['horde:administration:sessions'],
]);

$view = new Horde_View([
    'templatePath' => HORDE_TEMPLATES . '/admin',
]);
$view->addHelper('Horde_Core_View_Helper_Image');
$view->addHelper('Text');

try {
    $resolver = $injector->getInstance('Net_DNS2_Resolver');
    $s_info = [];

    $admin = new SessionAdministrator(
        $injector->getInstance(SessionHandler::class)
    );

    try {
        $sessionIds = $admin->listAll();
    } catch (CapabilityException $e) {
        // Backend cannot enumerate sessions (e.g. native files). Surface a
        // friendly error rather than crashing the admin page.
        $view->error = $e->getMessage();
        $sessionIds = [];
    }

    foreach ($sessionIds as $sessionId) {
        $sessionObj = $admin->load($sessionId);
        if ($sessionObj === null) {
            continue;
        }

        if (!$sessionObj instanceof SessionMetaInterface) {
            continue;
        }

        $id = (string) $sessionId;

        $userId = $sessionObj->getAuthenticatedUser();
        if ($userId === null) {
            continue;
        }

        $ts = $sessionObj->getAuthTimestamp();
        $tmp = [
            'auth' => implode(', ', $sessionObj->getAuthenticatedApps()),
            'browser' => $sessionObj->getBrowserFingerprint() ?? '',
            'id' => $id,
            'remotehost' => '[' . _("Unknown") . ']',
            'timestamp' => $ts !== null ? $ts->format('r') : '',
            'userid' => $userId,
        ];

        $remoteAddr = $sessionObj->getRemoteAddress();
        // Strip null bytes — existing sessions may contain tainted IPs
        // and PHP 8.x gethostbyaddr() throws ValueError on null bytes.
        if ($remoteAddr !== null) {
            $remoteAddr = trim(str_replace("\0", '', $remoteAddr));
        }
        if ($remoteAddr !== null && $remoteAddr !== '') {
            $host = null;
            if ($resolver) {
                try {
                    if ($resp = $resolver->query($remoteAddr, 'PTR')) {
                        $host = $resp->answer[0]->ptrdname;
                    }
                } catch (NetDNS2\Exception $e) {
                }
            }
            if (is_null($host)) {
                $host = @gethostbyaddr($remoteAddr);
            }
            $tmp['remotehost'] = $host . ' [' . $remoteAddr . '] ';
            $tmp['remotehostimage'] = Horde_Core_Ui_FlagImage::generateFlagImageByHost($host);
        }

        $s_info[] = $tmp;
    }

    $view->session_info = $s_info;
} catch (Horde_Exception $e) {
    $view->error = $e->getMessage();
}

$page_output->addScriptFile('tables.js', 'horde');
$page_output->header([
    'title' => _("Session Administration"),
]);
require HORDE_TEMPLATES . '/admin/menu.inc';
echo $view->render('sessions');
$page_output->footer();
