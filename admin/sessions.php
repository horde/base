<?php
/**
 * Sessions information.
 *
 * Copyright 2005-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', array(
    'permission' => array('horde:administration:sessions')
));

switch (Horde_Util::getFormData('action')) {
case 'kill':
    $sessionId = Horde_Util::getFormData('session');
    $info = $injector->getInstance('Horde_Core_Factory_SessionHandler')
        ->readSessionData($session->sessionHandler->read($sessionId));
    if ($session->sessionHandler->destroy($sessionId)) {
        if ($info && isset($info['userid'])) {
            $notification->push(
                sprintf(_("Sucessfully killed session of user \"%s\""), $info['userid']),
                'horde.success'
            );
        } else {
            $notification->push(
                sprintf(_("Sucessfully killed session %s"), $sessionId),
                'horde.success'
            );
        }
    } else {
        if ($info && isset($info['userid'])) {
            $notification->push(
                sprintf(_("Failed to kill session of user \"%s\""), $info['userid']),
                'horde.error'
            );
        } else {
            $notification->push(
                sprintf(_("Failed to kill session %s"), $sessionId),
                'horde.error'
            );
        }
    }
}
$view = new Horde_View(array(
    'templatePath' => HORDE_TEMPLATES . '/admin'
));
$view->addHelper('Horde_Core_View_Helper_Image');
$view->addHelper('Text');

try {
    $resolver = $injector->getInstance('Net_DNS2_Resolver');
    $s_info = array();

    foreach ($session->sessionHandler->getSessionsInfo() as $id => $data) {
        $tmp = array(
            'auth' => implode(', ', $data['apps']),
            'browser' => $data['browser'],
            'id' => $id,
            'remotehost' => '[' . _("Unknown") . ']',
            'timestamp' => date('r', $data['timestamp']),
            'userid' => $data['userid'],
            'kill' => Horde::selfUrl()->add(
                array('action' => 'kill', 'session' => $id)
            ),
        );

        if (!empty($data['remoteAddr'])) {
            $host = null;
            if ($resolver) {
                try {
                    if ($resp = $resolver->query($data['remoteAddr'], 'PTR')) {
                        $host = $resp->answer[0]->ptrdname;
                    }
                } catch (Net_DNS2_Exception $e) {}
            }
            if (is_null($host)) {
                $host = @gethostbyaddr($data['remoteAddr']);
            }
            $tmp['remotehost'] = $host . ' [' . $data['remoteAddr'] . '] ';
            $tmp['remotehostimage'] = Horde_Core_Ui_FlagImage::generateFlagImageByHost($host);
        }

        $s_info[] = $tmp;
    }

    $view->session_info = $s_info;
} catch (Horde_Exception $e) {
    $view->error = $e->getMessage();
}

$page_output->addScriptFile('tables.js', 'horde');
$page_output->header(array(
    'title' => _("Session Administration")
));
require HORDE_TEMPLATES . '/admin/menu.inc';
echo $view->render('sessions');
$page_output->footer();
