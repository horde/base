<?php

/**
 * Copyright 2005-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author Jason Felice <jason.m.felice@gmail.com>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

require_once __DIR__ . '/../lib/Application.php';
Horde_Registry::appInit('horde', ['nologintasks' => true]);

// Make sure auth backend allows passwords to be reset.
$auth = $injector->getInstance('Horde_Core_Factory_Auth')->create();
if (!$auth->hasCapability('update')) {
    $notification->push(_("Changing your password is not supported with the current configuration.  Contact your administrator."), 'horde.error');
    $registry->getServiceLink('login')->add('url', Horde_Util::getFormData('url'))->redirect();
}

$vars = $injector->getInstance('Horde_Variables');

$title = _("Change Your Password");
$form = new Horde_Form($vars, $title);
$form->setButtons(_("Continue"));

$form->addVariable(_("Old password"), 'old_password', 'password', true);
$form->addVariable(_("New password"), 'password_1', 'password', true);
$form->addVariable(_("Retype new password"), 'password_2', 'password', true);

if ($form->validate($vars)) {
    $info = $form->getInfo($vars);

    if ($registry->getAuthCredential('password') != $info['old_password']) {
        $notification->push(_("Old password is not correct."), 'horde.error');
    } elseif ($info['password_1'] != $info['password_2']) {
        $notification->push(_("New passwords don't match."), 'horde.error');
    } elseif ($info['old_password'] == $info['password_1']) {
        $notification->push(_("Old and new passwords must be different."), 'horde.error');
    } else {
        try {
            $auth->updateUser($registry->getAuth(), $registry->getAuth(), ['password' => $info['password_1']]);

            $notification->push(_("Password changed successfully."), 'horde.success');

            $registry->getLogoutUrl([
                'msg' => _("Your password has been succesfully changed. You need to re-login to the system with your new password."),
                'reason' => Horde_Auth::REASON_MESSAGE,
            ])->redirect();
        } catch (Horde_Auth_Exception $e) {
            $notification->push(sprintf(_("Error updating password: %s"), $e->getMessage()), 'horde.error');
        }
    }
}

$vars->remove('old_password');
$vars->remove('password_1');
$vars->remove('password_2');

$page_output->topbar = $page_output->sidebar = false;
$page_output->header([
    'title' => $title,
]);
$notification->notify(['listeners' => 'status']);
$form->renderActive(new Horde_Form_Renderer(), $vars, Horde::url('services/changepassword.php'), 'post');
$page_output->footer();
