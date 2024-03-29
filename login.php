<?php
/**
 * Horde login page.
 *
 * URL Parameters:
 *   - app: The app to login to.
 *   - horde_logout_token: TODO
 *   - horde_user: TODO
 *   - logout_msg: Logout message.
 *   - logout_reason: Logout reason (Horde_Auth or Horde_Core_Auth_Wrapper
 *                    constant).
 *   - url: The url to redirect to after auth.
 *
 * Copyright 1999-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Chuck Hagenbuch <chuck@horde.org>
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

/* Add anchor to outgoing URL. */
function _addAnchor($url, $type, $vars, $url_anchor = null)
{
    switch ($type) {
    case 'param':
        if (!is_null($url_anchor)) {
            $url->anchor = $url_anchor;
        }
        break;

    case 'url':
        $anchor = $vars->anchor_string;
        if (!empty($anchor)) {
            $url->setAnchor($anchor);
        } else {
            return _addAnchor($url, 'param', $vars, $url_anchor);
        }
        break;
    }

    return $url;
}


/* Try to login - if we are doing auth to an app, we need to auth to
 * Horde first or else we will lose the session. Ignore any auth errors.
 * Transparent authentication is handled by the Horde_Application::
 * constructor. */
require_once __DIR__ . '/lib/Application.php';
try {
    Horde_Registry::appInit('horde', array(
        'authentication' => 'none',
        'nologintasks' => true
    ));
} catch (Horde_Exception_AuthenticationFailure $e) {}

$is_auth = $registry->isAuthenticated();
$vars = $injector->getInstance('Horde_Variables');

/* This ensures index.php doesn't pick up the 'url' parameter. */
$horde_login_url = '';

/* Get an Auth object. */
$auth = $injector->getInstance('Horde_Core_Factory_Auth')->create(($is_auth && $vars->app) ? $vars->app : null);

/* Get URL/Anchor strings now. */
if ($url_in = Horde::verifySignedUrl($vars->url)) {
    $url_in = new Horde_Url($url_in);
    $url_anchor = $url_in->anchor;
    $url_in->anchor = null;
} else {
    $url_anchor = $url_in = null;
}

if (!($logout_reason = $auth->getError())) {
    $logout_reason = $vars->logout_reason;
}

/* Change language. */
if (!$is_auth && !$prefs->isLocked('language') && $vars->new_lang) {
    $registry->setLanguageEnvironment($vars->new_lang);
}

if ($logout_reason) {
    if ($is_auth) {
        try {
            $session->checkToken($vars->horde_logout_token);
        } catch (Horde_Exception $e) {
            $notification->push($e, 'horde.error');
            require HORDE_BASE . '/index.php';
            exit;
        }
        $is_auth = null;

        Horde::log(
            sprintf(
                'User %s logged out of Horde (%s)%s',
                $registry->getAuth(),
		$_SERVER['REMOTE_ADDR'],
		empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])'
            ),
            'NOTICE'
        );
    }

    $registry->clearAuth();

    /* Reset notification handler now, since it may still be using a status
     * handler that is no longer valid. */
    $notification->detach('status');
    $notification->attach('status');

    /* Redirect the user on logout if redirection is enabled and this is an
     * an intended logout. */
    if (($logout_reason == Horde_Auth::REASON_LOGOUT) &&
        !empty($conf['auth']['redirect_on_logout'])) {
        $logout_url = new Horde_Url($conf['auth']['redirect_on_logout'], true);
        if (!isset($_COOKIE[session_name()])) {
            $logout_url->add(session_name(), session_id());
        }
        _addAnchor($logout_url, 'url', $vars, $url_anchor)->redirect();
    }

    $session->setup();

    /* Explicitly set language in un-authenticated session. */
    $registry->setLanguage($GLOBALS['language']);
} elseif (Horde_Util::getPost('login_post') ||
          Horde_Util::getPost('login_button')) {
    $select_view = Horde_Util::getPost('horde_select_view');
    if ($select_view == 'mobile_nojs') {
        $nojs = true;
        $select_view = 'mobile';
    } else {
        $nojs = false;
    }

    /* Get the login params from the login screen. */
    $auth_params = array(
        'password' => Horde_Util::getPost('horde_pass'),
        'mode' => $select_view
    );

    try {
        $result = $auth->getLoginParams();
        foreach (array_keys($result['params']) as $val) {
            $auth_params[$val] = Horde_Util::getPost($val);
        }
    } catch (Horde_Exception $e) {}

    if ($auth->authenticate(Horde_Util::getPost('horde_user'), $auth_params)) {
        Horde::log(
            sprintf(
                'Login success for %s to %s (%s)%s',
                $registry->getAuth(),
                ($vars->app && $is_auth) ? $vars->app : 'horde',
		$_SERVER['REMOTE_ADDR'],
		empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])'
            ),
            'NOTICE'
        );

        if (!$is_auth && $nojs) {
            $notification->push(_("JavaScript is either disabled or not available on your browser. You are restricted to the minimal view."));
        }

        if (!empty($url_in)) {
            /* $horde_login_url is used by horde/index.php to redirect to URL
             * without the need to redirect to horde/index.php also. */
            $horde_login_url = Horde::url(_addAnchor($url_in->remove(session_name()), 'url', $vars), true);
        }

        /* Do password change request on initial login only. */
        if (!$is_auth && $registry->passwordChangeRequested()) {
            $notification->push(_("Your password has expired."), 'horde.message');

            if ($auth->hasCapability('update')) {
                Horde::url('services/changepassword.php')->redirect();
            }
        }

        require HORDE_BASE . '/index.php';
        exit;
    }

    $logout_reason = $auth->getError();

    Horde::log(
        sprintf(
            'FAILED LOGIN for %s to %s (%s)%s',
            $vars->horde_user,
            ($vars->app && $is_auth) ? $vars->app : 'horde',
	    $_SERVER['REMOTE_ADDR'],
	    empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])'
        ),
        'ERR'
    );
}

/* Build the list of necessary login parameters.
 * Need to wait until after we set language to get login parameters. */
$loginparams = array(
    'horde_user' => array(
        'label' => _("Username"),
        'type' => 'text',
        'value' => $vars->horde_user
    ),
    'horde_pass' => array(
        'label' => _("Password"),
        'type' => 'password'
    )
);
$js_code = array(
    'HordeLogin.user_error' => _("Please enter a username."),
    'HordeLogin.pass_error' => _("Please enter a password.")
);
$js_files = array(
    array('login.js', 'horde')
);

if (!empty($GLOBALS['conf']['user']['select_view'])) {
    $js_code['HordeLogin.pre_sel'] = $vars->get('horde_select_view', isset($_COOKIE['default_horde_view']) ? $_COOKIE['default_horde_view'] : 'auto');
    $loginparams['horde_select_view'] = array(
        'type' => 'horde_select_view'
    );
}

try {
    $result = $auth->getLoginParams();
    $loginparams = array_filter(array_merge($loginparams, $result['params']));
    $js_code = array_merge($js_code, $result['js_code']);
    $js_files = array_merge($js_files, $result['js_files']);
} catch (Horde_Exception $e) {}

/* If we currently are authenticated, and are not trying to authenticate to
 * an application, redirect to initial page. This is done in index.php.
 * If we are trying to authenticate to an application, but don't have to,
 * redirect to the requesting URL. */
if ($is_auth) {
    if (!$vars->app) {
        require HORDE_BASE . '/index.php';
        exit;
    } elseif ($url_in &&
              $registry->isAuthenticated(array('app' => $vars->app))) {
        _addAnchor($url_in, 'param', null, $url_anchor)->redirect();
    }
}

/* Redirect the user if an alternate login page has been specified. */
if (!empty($conf['auth']['alternate_login'])) {
    $url = new Horde_Url($conf['auth']['alternate_login'], true);
    if ($vars->app) {
        $url->add('app', $vars->app);
    }
    if (!isset($_COOKIE[session_name()])) {
        $url->add(session_name(), session_id());
    }

    if (empty($url_in)) {
        $url_in = Horde::selfUrl(true, true, true);
    }
    $anchor = _addAnchor($url_in, 'param', $vars, $url_anchor);
    $found = false;
    foreach ($url->parameters as $key => $value) {
        if (strpos($value, '%u') !== false) {
            $url->parameters[$key] = str_replace('%u', $anchor, $value);
            $found = true;
        }
    }
    if (!$found) {
        $url->add('url', $anchor);
    }
    _addAnchor($url, 'url', $vars, $url_anchor)->redirect();
}

/* Build the <select> widget containing the available languages. */
if (!$is_auth && !$prefs->isLocked('language')) {
    $langs = array();
    foreach ($registry->nlsconfig->languages as $key => $val) {
        if ($registry->nlsconfig->validLang($key)) {
            $langs[] = array(
                'sel' => ($key == $GLOBALS['language']),
                'val' => $key,
                // Language names are already encoded.
                'name' => $val
            );
        }
    }
}

$title = _("Log in");

$reason = null;
switch ($logout_reason) {
case Horde_Auth::REASON_SESSION:
    $reason = _("Your session has expired. Please login again.");
    break;

case Horde_Core_Auth_Application::REASON_SESSIONIP:
    $reason = _("Your Internet Address has changed since the beginning of your session. To protect your security, you must login again.");
    break;

case Horde_Core_Auth_Application::REASON_BROWSER:
    $reason = _("Your browser appears to have changed since the beginning of your session. To protect your security, you must login again.");
    break;

case Horde_Core_Auth_Application::REASON_SESSIONMAXTIME:
    $reason = _("Your session length has exceeded the maximum amount of time allowed. Please login again.");
    break;

case Horde_Auth::REASON_LOGOUT:
    $reason = _("You have been logged out.");
    break;

case Horde_Auth::REASON_FAILED:
    $reason = _("Login failed.");
    break;

case Horde_Auth::REASON_BADLOGIN:
    $reason = _("Login failed because your username or password was entered incorrectly.");
    break;

case Horde_Auth::REASON_EXPIRED:
    $reason = _("Your login has expired.");
    break;

case Horde_Auth::REASON_LOCKED:
case Horde_Auth::REASON_MESSAGE:
    if (!($reason = $auth->getError(true))) {
        $reason = $vars->logout_msg;
    }
    break;
}
if ($reason) {
    $notification->push(str_replace('<br />', ' ', $reason), 'horde.message');
}

$loginurl = Horde::url('login.php', false, array(
    'append_session' => ($is_auth ? 0 : -1),
    'force_ssl' => true
));

$page_output->sidebar = false;
$page_output->topbar = (bool)$is_auth;
$page_output->addInlineJsVars($js_code);

if ($browser->isMobile() &&
    (!isset($conf['user']['force_view']) ||
     !in_array($conf['user']['force_view'], array('basic', 'dynamic')))) {
    $loginparams['horde_user']['value'] = htmlspecialchars($loginparams['horde_user']['value']);
    $view = new Horde_View(array(
        'templatePath' => HORDE_TEMPLATES . '/login'
    ));
    $view->addHelper('Text');

    /* Build the <select> widget containing the available languages. */
    if (!$is_auth && !$prefs->isLocked('language')) {
        $tmp = array();
        foreach ($langs as $val) {
            $tmp[$val['val']] = array(
                'name' => $val['name'],
                'selected' => $val['sel']
            );
        }
        $loginparams['new_lang'] = array(
            'label' => _("Language"),
            'type' => 'select',
            'value' => $tmp
        );
    }

    $view->anchor = $vars->anchor_string;
    $view->app = $vars->app;
    $view->loginparams_auth = array_intersect_key($loginparams, array('horde_user' => 1, 'horde_pass' => 1));
    $view->loginparams_other = array_diff_key($loginparams, array('horde_user' => 1, 'horde_pass' => 1));
    $view->loginurl = $loginurl;
    $view->title = $title;
    $view->url = $vars->url;
    try {
        $view->motd = $registry
            ->loadConfigFile('motd.php', 'motd', 'horde')
            ->config['motd'];
    } catch (Horde_Exception $e) {
    }

    if ($browser->hasFeature('ajax')) {
        $page_output->addScriptFile('smartmobile-login.js', 'horde');

        /* Ensure that we are using the smartmobile status listener. */
        $notification->detach('status');
        $notification->attach('status', null, 'Horde_Core_Notification_Listener_SmartmobileStatus');

        $view_type = $registry::VIEW_SMARTMOBILE;
    } else {
        $view_type = $registry::VIEW_MINIMAL;
    }

    $page_output->header(array(
        'title' => $title,
        'view' => $view_type
    ));
    $notification->notify(array('listeners' => 'status'));
    echo $view->render('smartmobile');
} else {
    $auth_label = ($is_auth && $auth)
        ? sprintf(_("Login to %s"), $registry->get('name', $vars->app))
        : null;

    foreach ($js_files as $val) {
        $page_output->addScriptFile($val[0], $val[1]);
    }

    $page_output->header(array(
        'body_class' => 'modal-form',
        'title' => $title
    ));
    require $registry->get('templates', 'horde') . '/login/login.inc';
}

$page_output->footer();
