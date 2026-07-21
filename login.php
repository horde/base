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
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
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

use Horde\Core\Session\HordeSession;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token;
use Horde\Util\Util;
use Horde\Util\Variables;
use Psr\Log\LoggerInterface;

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

/**
 * Collect login params (and optionally posted values) from every
 * registered application that declares the 'loginparams' auth capability,
 * independently of which app/driver is currently authenticating Horde
 * itself.
 *
 * This lets an app's login UI (e.g. a mail server selector) appear and be
 * honored even when Horde's own auth driver is unrelated to that app
 * (LDAP, SQL, ...).
 *
 * @param Horde_Injector $injector
 * @param Horde_Registry $registry
 * @param LoggerInterface|null $logger  Used to record unexpected failures
 *                                      per app. Null-safe so the helper
 *                                      is still callable before the
 *                                      request logger is resolved.
 * @param boolean $collectPost  Also read posted values for each collected
 *                              field.
 *
 * @return array{params: array, js_code: array, js_files: array,
 *               posted: array<string, array>} 'posted' is keyed by app.
 */
function _collectAppLoginParams($injector, $registry, $logger = null, $collectPost = false)
{
    $params = $js_code = $js_files = [];
    $posted = [];

    // perms=null (not 0) is required to bypass Horde_Registry's permission
    // check here — we're pre-authentication, there is no user to check
    // permissions against yet.
    foreach ($registry->listApps(null, false, null) as $app) {
        if ($app === 'horde') {
            continue;
        }

        try {
            $appAuth = $injector->getInstance('Horde_Core_Factory_Auth')->create($app);
            if (!$appAuth->hasCapability('loginparams')) {
                continue;
            }

            $result = $appAuth->getLoginParams();
            $params = array_merge($params, $result['params'] ?? []);
            $js_code = array_merge($js_code, $result['js_code'] ?? []);
            $js_files = array_merge($js_files, $result['js_files'] ?? []);

            if ($collectPost) {
                foreach (array_keys($result['params'] ?? []) as $key) {
                    $posted[$app][$key] = Util::getPost($key);
                }
            }
        } catch (Horde_Exception | \Horde\Exception\HordeThrowable $e) {
            // Expected: this app declined to provide login params (not
            // configured, not applicable, etc). Skip it silently, same
            // behavior as the pre-existing single-app getLoginParams() call
            // below.
            continue;
        } catch (\Throwable $e) {
            // Unexpected failure (misconfigured DI, broken app code, ...).
            // Do not let one broken app take down the login page for
            // everyone, but do log it so operators see the problem.
            if ($logger !== null) {
                $logger->error(
                    'login.php: collecting login params from app ' . $app
                    . ' failed: ' . $e->getMessage(),
                    ['exception' => $e]
                );
            }
            continue;
        }
    }

    return [
        'params' => $params,
        'js_code' => $js_code,
        'js_files' => $js_files,
        'posted' => $posted,
    ];
}

/* Try to login - if we are doing auth to an app, we need to auth to
 * Horde first or else we will lose the session. Ignore any auth errors.
 * Transparent authentication is handled by the Horde_Application::
 * constructor. */
require_once __DIR__ . '/lib/Application.php';
try {
    Horde_Registry::appInit('horde', [
        'authentication' => 'none',
        'nologintasks' => true,
    ]);
} catch (Horde_Exception_AuthenticationFailure $e) {
}

$is_auth = $registry->isAuthenticated();
$vars = $injector->getInstance(Variables::class);
$logger = $injector->getInstance(LoggerInterface::class);
$loginHandler = $injector->getInstance(Horde\Horde\Login::class);

/* This ensures index.php doesn't pick up the 'url' parameter. */
$horde_login_url = '';

/* Get an Auth object. */
$auth = $injector->getInstance('Horde_Core_Factory_Auth')->create(($is_auth && $vars->app) ? $vars->app : null);

/* Get URL/Anchor strings now. */
if ($url_in = Horde::verifySignedUrl($vars->url)) {
    $url_in = new Horde_Url($url_in);
    $url_anchor = $url_in->anchor;
    $url_in->anchor = '';
} else {
    $url_anchor = $url_in = null;
}

if (!($logout_reason = $auth->getError())) {
    $logout_reason = $vars->logout_reason;
}

/* Handle error parameter from POST-Redirect-GET pattern */
if (!$logout_reason && $vars->error) {
    // Map URL error code back to auth reason constant
    $logout_reason = match ($vars->error) {
        'badlogin' => Horde_Auth::REASON_BADLOGIN,
        'expired' => Horde_Auth::REASON_EXPIRED,
        'locked' => Horde_Auth::REASON_LOCKED,
        'required' => Horde_Auth::REASON_BADLOGIN,
        'secondfactor' => Horde_Auth::REASON_BADLOGIN,
        default => Horde_Auth::REASON_FAILED,
    };
}

/* Handle logout_reason parameter - map string to constant */
if ($logout_reason && is_string($logout_reason)) {
    $logout_reason = match ($logout_reason) {
        'logout' => Horde_Auth::REASON_LOGOUT,
        'badlogin' => Horde_Auth::REASON_BADLOGIN,
        'expired' => Horde_Auth::REASON_EXPIRED,
        'locked' => Horde_Auth::REASON_LOCKED,
        'failed' => Horde_Auth::REASON_FAILED,
        'message' => Horde_Auth::REASON_MESSAGE,
        'session' => Horde_Auth::REASON_SESSION,
        default => is_numeric($logout_reason) ? (int) $logout_reason : null,
    };
}

/* Change language. */
if (!$is_auth && !$prefs->isLocked('language') && $vars->new_lang) {
    $registry->setLanguageEnvironment($vars->new_lang);
}

if ($logout_reason) {
    if ($is_auth) {
        try {
            $tokenService = $injector->getInstance(Token::class);
            $valid = $tokenService->isValid(
                (string) $vars->horde_logout_token,
                HordeSession::CSRF_SEED
            );
            if (!$valid) {
                throw new Horde_Exception('Invalid token!');
            }
        } catch (TokenException $e) {
            $notification->push(new Horde_Exception('Invalid token!'), 'horde.error');
            require HORDE_BASE . '/index.php';
            exit;
        } catch (Horde_Exception $e) {
            $notification->push($e, 'horde.error');
            require HORDE_BASE . '/index.php';
            exit;
        }
        $is_auth = null;

        $logger->notice(sprintf(
            'User %s logged out of Horde (%s)%s',
            $registry->getAuth(),
            $_SERVER['REMOTE_ADDR'],
            empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])'
        ));
    }

    $registry->clearAuth();

    /* Reset notification handler now, since it may still be using a status
     * handler that is no longer valid. */
    $notification->detach('status');
    $notification->attach('status');

    /* Redirect the user on logout if redirection is enabled and this is an
     * an intended logout. */
    if (($logout_reason == Horde_Auth::REASON_LOGOUT)
        && !empty($conf['auth']['redirect_on_logout'])) {
        $logout_url = new Horde_Url($conf['auth']['redirect_on_logout'], true);
        if (!isset($_COOKIE[session_name()])) {
            $logout_url->add(session_name(), session_id());
        }
        _addAnchor($logout_url, 'url', $vars, $url_anchor)->redirect();
    }

    $session->setup();

    /* Explicitly set language in un-authenticated session. */
    $registry->setLanguage($GLOBALS['language']);

    /* Reload preferences as anonymous/guest user to get default/global theme. */
    try {
        $prefs = $injector->getInstance('Horde_Prefs');
        // Force prefs to reload for anonymous user (cleared by clearAuth above)
        $prefs->retrieve();
    } catch (Horde_Exception $e) {
        // Ignore - theme will use system default
    }
} elseif (Util::getPost('login_post')
          || Util::getPost('login_button')) {
    $select_view = Util::getPost('horde_select_view');
    if ($select_view == 'mobile_nojs') {
        $nojs = true;
        $select_view = 'mobile';
    } else {
        $nojs = false;
    }

    /* Get the login params from the login screen. */
    $auth_params = [
        'password' => Util::getPost('horde_pass'),
        'mode' => $select_view,
    ];

    try {
        $result = $auth->getLoginParams();
        foreach (array_keys($result['params']) as $val) {
            $auth_params[$val] = Util::getPost($val);
        }
    } catch (Horde_Exception $e) {
    }

    // Also collect any posted values for apps declaring 'loginparams',
    // independently of the current Horde auth driver, so a selection (e.g.
    // an IMP mail-server choice) submitted alongside the primary login isn't
    // silently dropped.
    $appLoginParams = _collectAppLoginParams($injector, $registry, $logger, true);
    $app_login_selection = array_filter($appLoginParams['posted']);

    // TODO: Factor out into login handler class
    // First check if we need to validate the second factor.
    $authUser = (string) Util::getPost('horde_user');
    $errorSecondFactor = false;
    if ($loginHandler->secondFactorSupported()) {
        $message = null;
        try {
            $authSecondFactor = (string) Util::getPost('horde_secondfactor');
            $message = $loginHandler->secondFactorApi('blockLogin', 'Second factor API error', [
                $authUser,
                $authSecondFactor,
            ]);
            if ($message) {
                $errorSecondFactor = Horde_Auth::REASON_MESSAGE;
            }
        } catch (Horde_Exception $e) {
            $errorSecondFactor = Horde_Auth::REASON_BADLOGIN;
        }

        if ($errorSecondFactor !== false) {
            $auth->setError($errorSecondFactor, $message);
        }
    }

    if ($errorSecondFactor === false && $auth->authenticate($authUser, $auth_params)) {
        $logger->notice(sprintf(
            'Login success for %s to %s (%s)%s',
            $registry->getAuth(),
            ($vars->app && $is_auth) ? $vars->app : 'horde',
            $_SERVER['REMOTE_ADDR'],
            empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])'
        ));

        if (!$is_auth && $nojs) {
            $notification->push(_("JavaScript is either disabled or not available on your browser. You are restricted to the minimal view."));
        }

        if (!empty($url_in)) {
            /* $horde_login_url is used by horde/index.php to redirect to URL
             * without the need to redirect to horde/index.php also. */
            $horde_login_url = Horde::url(_addAnchor($url_in->remove(session_name()), 'url', $vars), true);
        }

        if (!empty($app_login_selection)) {
            // Store per-app posted login params in the session so that an app's
            // own transparent-auth logic can honor the user's selection later,
            // even though Horde itself authenticated through a different driver.
            $session->set('horde', 'login_app_params', $app_login_selection);
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

    // Authentication failed - use Post-Redirect-Get pattern
    $error_reason = $auth->getError();

    $logger->error(sprintf(
        'FAILED LOGIN for %s to %s (%s)%s',
        $vars->horde_user,
        ($vars->app && $is_auth) ? $vars->app : 'horde',
        $_SERVER['REMOTE_ADDR'],
        empty($_SERVER['HTTP_X_FORWARDED_FOR']) ? '' : ' (forwarded for [' . $_SERVER['HTTP_X_FORWARDED_FOR'] . '])'
    ));

    // Map auth error reason to URL-safe error code
    $error_code = match ($error_reason) {
        Horde_Auth::REASON_BADLOGIN => 'badlogin',
        Horde_Auth::REASON_EXPIRED => 'expired',
        Horde_Auth::REASON_LOCKED => 'locked',
        Horde_Auth::REASON_MESSAGE => 'secondfactor',
        default => 'failed',
    };

    // Check for empty credentials (special case)
    if (empty($authUser) || empty($auth_params['password'])) {
        $error_code = 'required';
    }

    // Build redirect URL with error parameter
    $redirect_url = Horde::url('login.php', true);
    $redirect_url->add('error', $error_code);

    // Preserve original URL parameter if present
    if (!empty($url_in)) {
        $redirect_url->add('url', $vars->url);
    }

    // Preserve app parameter if present
    if (!empty($vars->app)) {
        $redirect_url->add('app', $vars->app);
    }

    // Redirect to login page with error (PRG pattern)
    $redirect_url->redirect();
    exit;
}

/* Build the list of necessary login parameters.
 * Need to wait until after we set language to get login parameters. */
$loginparams = $loginHandler->buildLoginParams();

$js_code = [
    'HordeLogin.user_error' => _("Please enter a username."),
    'HordeLogin.pass_error' => _("Please enter a password."),
];
$js_files = [
    ['login.js', 'horde'],
];

if (!empty($GLOBALS['conf']['user']['select_view'])) {
    $js_code['HordeLogin.pre_sel'] = $vars->get('horde_select_view', $_COOKIE['default_horde_view'] ?? 'auto');

    // Build mode options based on configuration
    $modeOptions = [
        'auto' => ['name' => _("Automatic")],
    ];

    // Add Basic mode if enabled (default: disabled)
    if (!empty($GLOBALS['conf']['user']['select_basic_view'])) {
        $modeOptions['basic'] = ['name' => _("Basic")];
    }

    // Always include Dynamic mode
    $modeOptions['dynamic'] = ['name' => _("Dynamic")];

    // Add Minimal mode if enabled (default: disabled)
    if (!empty($GLOBALS['conf']['user']['select_minimal_view'])) {
        $modeOptions['mobile'] = ['name' => _("Mobile (Minimal)")];
    }

    // Always include mobile_nojs so JavaScript can remove it
    // (JavaScript expects this option to exist and removes it on load)
    $modeOptions['mobile_nojs'] = ['name' => _("Mobile (No JavaScript)")];

    // Always include Smartmobile mode
    $modeOptions['smartmobile'] = ['name' => _("Mobile (Smartphone/Tablet)")];

    $loginparams['horde_select_view'] = [
        'type'   => 'select',
        'label'  => _("Mode"),
        'value'  => $modeOptions,
        'div'    => [
            'id'    => 'horde_select_view_div',
            'style' => 'display:none',
        ],
    ];
}

try {
    $result = $auth->getLoginParams();
    $loginparams = array_filter(array_merge($loginparams, $result['params']));
    $js_code = array_merge($js_code, $result['js_code']);
    $js_files = array_merge($js_files, $result['js_files']);
} catch (Horde_Exception $e) {
}

// Also render login params for any app declaring 'loginparams', not
// just the app currently bound to $auth.
$appLoginParams = _collectAppLoginParams($injector, $registry, $logger);
$loginparams = array_filter(array_merge($loginparams, $appLoginParams['params']));
$js_code = array_merge($js_code, $appLoginParams['js_code']);
$js_files = array_merge($js_files, $appLoginParams['js_files']);

/* If we currently are authenticated, and are not trying to authenticate to
 * an application, redirect to initial page. This is done in index.php.
 * If we are trying to authenticate to an application, but don't have to,
 * redirect to the requesting URL. */
if ($is_auth) {
    if (!$vars->app) {
        require HORDE_BASE . '/index.php';
        exit;
    } elseif ($url_in
              && $registry->isAuthenticated(['app' => $vars->app])) {
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
    $langs = [];
    foreach ($registry->nlsconfig->languages as $key => $val) {
        if ($registry->nlsconfig->validLang($key)) {
            $langs[] = [
                'sel' => ($key == $GLOBALS['language']),
                'val' => $key,
                // Language names are already encoded.
                'name' => $val,
            ];
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

$loginurl = Horde::url('login.php', false, [
    'append_session' => ($is_auth ? 0 : -1),
    'force_ssl' => true,
]);

$page_output->sidebar = false;
$page_output->topbar = (bool) $is_auth;
$page_output->addInlineJsVars($js_code);

// Always use responsive login (unified design for mobile and desktop)
// Old smartmobile and desktop templates removed in favor of single responsive design

// Use responsive login display
$responsiveAssets = new Horde\Core\Assets\ResponsiveAssets(new Horde\Core\Config\RegistryState($registry->applications));

// Get webroot and themes URI
$webroot = $registry->get('webroot', 'horde');
$themesUri = $registry->get('themesuri', 'horde');
$jsUri = $registry->get('jsuri', 'horde');
$theme = $responsiveAssets->getTheme();
$cssUrls = $responsiveAssets->getCssUrls('horde');

// Load responsive login JavaScript (vanilla JavaScript, no Prototype.js)
$jsUrls = [$jsUri . '/login_responsive.js'];

// Build error HTML if reason exists
$errorHtml = '';
if ($reason) {
    // Determine appropriate alert class based on logout reason
    $alertClass = 'alert-error'; // Default to error

    switch ($logout_reason) {
        case Horde_Auth::REASON_LOGOUT:
            $alertClass = 'alert-info';
            break;
        case Horde_Auth::REASON_MESSAGE:
            // REASON_MESSAGE is used for informational messages like password change
            $alertClass = 'alert-success';
            break;
        case Horde_Auth::REASON_SESSION:
        case Horde_Core_Auth_Application::REASON_SESSIONIP:
        case Horde_Core_Auth_Application::REASON_BROWSER:
        case Horde_Core_Auth_Application::REASON_SESSIONMAXTIME:
            $alertClass = 'alert-info';
            break;
            // REASON_FAILED, REASON_BADLOGIN, REASON_EXPIRED, REASON_LOCKED use default alert-error
    }

    $errorHtml = '<div class="alert ' . $alertClass . '">' . htmlspecialchars($reason, ENT_QUOTES) . '</div>';
}

// Form fields - render ALL fields from $loginparams (includes username, password, 2FA, mode selector, etc.)
$formFields = '';

foreach ($loginparams as $key => $param) {
    // Skip language selector - it's handled separately below
    if ($key === 'new_lang') {
        continue;
    }

    $label = $param['label'] ?? ucfirst($key);
    $type = $param['type'] ?? 'text';
    $value = $param['value'] ?? '';

    // Build div attributes if specified
    $divAttrs = '';
    if (isset($param['div'])) {
        foreach ($param['div'] as $attr => $attrValue) {
            $divAttrs .= ' ' . htmlspecialchars($attr, ENT_QUOTES) . '="' . htmlspecialchars($attrValue, ENT_QUOTES) . '"';
        }
    }

    if ($type === 'select') {
        $formFields .= '<div class="form-group"' . $divAttrs . '>';
        $formFields .= '<label for="' . htmlspecialchars($key, ENT_QUOTES) . '" class="form-label">' . htmlspecialchars($label, ENT_QUOTES) . '</label>';
        $formFields .= '<select id="' . htmlspecialchars($key, ENT_QUOTES) . '" name="' . htmlspecialchars($key, ENT_QUOTES) . '" class="form-input">';

        foreach ($param['value'] ?? [] as $optKey => $optVal) {
            // Skip null values (separators/disabled options)
            if ($optVal === null) {
                continue;
            }
            if (is_array($optVal)) {
                $selected = ($optVal['selected'] ?? false) ? ' selected' : '';
                // Ensure $optKey is scalar for htmlspecialchars
                $safeKey = is_scalar($optKey) ? (string) $optKey : '';
                $safeName = is_scalar($optVal['name'] ?? null) ? (string) ($optVal['name'] ?? $safeKey) : $safeKey;
                $formFields .= '<option value="' . htmlspecialchars($safeKey, ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars($safeName, ENT_QUOTES) . '</option>';
            }
        }
        $formFields .= '</select></div>';
    } elseif ($type === 'text' || $type === 'password') {
        $inputType = $type;
        // Ensure value is string - arrays should not be used for text/password fields
        $stringValue = is_array($value) ? '' : (string) $value;

        // Security: Never pre-fill password fields
        if ($type === 'password') {
            $inputValue = '';
        } elseif ($key === 'horde_user') {
            // Only pre-fill username after failed login (escaped for XSS prevention)
            $inputValue = htmlspecialchars($vars->horde_user ?? $stringValue, ENT_QUOTES);
        } else {
            // Other text fields: escape for XSS prevention
            $inputValue = htmlspecialchars($stringValue, ENT_QUOTES);
        }

        // Build extra HTML attributes from param definition
        $extra = $param['extra'] ?? [];
        if (empty($extra) && $type === 'text') {
            $extra = ['autocapitalize' => 'off', 'autocorrect' => 'off'];
        }
        $extraAttrs = '';
        foreach ($extra as $attrName => $attrValue) {
            $extraAttrs .= ' ' . htmlspecialchars($attrName, ENT_QUOTES) . '="' . htmlspecialchars($attrValue, ENT_QUOTES) . '"';
        }

        $formFields .= '<div class="form-group"' . $divAttrs . '>';
        $formFields .= '<label for="' . htmlspecialchars($key, ENT_QUOTES) . '" class="form-label">' . htmlspecialchars($label, ENT_QUOTES) . '</label>';
        $formFields .= '<input type="' . $inputType . '" id="' . htmlspecialchars($key, ENT_QUOTES) . '" name="' . htmlspecialchars($key, ENT_QUOTES) . '" class="form-input" value="' . $inputValue . '"' . $extraAttrs . ' />';
        $formFields .= '</div>';
    }
}

// Language selector if available
$languageSelector = '';
if (!$is_auth && !$prefs->isLocked('language') && !empty($langs)) {
    $languageSelector = '<div class="form-group">
        <label for="new_lang" class="form-label">' . htmlspecialchars(_("Language"), ENT_QUOTES) . '</label>
        <select id="new_lang" name="new_lang" class="form-input">';
    foreach ($langs as $lang) {
        $selected = $lang['sel'] ? ' selected' : '';
        // Language names are already HTML-encoded, don't double-encode
        $languageSelector .= '<option value="' . htmlspecialchars($lang['val'], ENT_QUOTES) . '"' . $selected . '>'
            . $lang['name'] . '</option>';
    }
    $languageSelector .= '</select></div>';
}

$passwordResetLink = '';
// Ensure these are always strings, not arrays (in case of malicious input like ?app[]=foo)
$app = is_string($vars->app) ? $vars->app : 'horde';
$url = is_string($vars->url) ? $vars->url : '';
$anchor_string = is_string($vars->anchor_string) ? $vars->anchor_string : '';

// Fetch OAuth providers for login buttons
$oauthProviders = [];
if (!empty($conf['oauth_login']['enabled'])) {
    try {
        $providerConfigRepo = $injector->getInstance(Horde\Core\Service\OAuthProviderConfigRepository::class);
        foreach ($providerConfigRepo->listEnabled() as $provider) {
            if (!empty($provider['client_id']) && ($provider['type'] ?? '') !== 'service_app') {
                $oauthProviders[] = $provider;
            }
        }
    } catch (Throwable $e) {
    }
}
$oauthLoginBaseUrl = $webroot . '/auth/oauth/login';

// Simple escape function for the template
$escape = function ($str) {
    if (is_array($str)) {
        return '';
    }
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

// Output the responsive template directly
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Horde</title>
<?php foreach ($cssUrls as $cssUrl): ?>
    <link rel="stylesheet" href="<?php echo $escape($cssUrl) ?>">
<?php endforeach; ?>
</head>
<body class="horde-responsive login-page">
    <div class="login-card card">
        <div class="login-logo">
            <img src="<?php echo $escape($themesUri) ?>/<?php echo $escape($theme) ?>/graphics/logo.png" alt="Horde">
        </div>

        <div class="card-header">
            <h1 class="card-title">Welcome to Horde</h1>
            <p class="card-subtitle">Sign in to continue</p>
        </div>

        <?php echo $errorHtml ?>

        <form method="post" action="<?php echo $escape($webroot) ?>/login.php" id="horde_login">
            <input type="hidden" name="login_post" value="1" />
            <input type="hidden" id="login_post" value="0" />
            <input type="hidden" name="url" value="<?php echo $escape($url) ?>">
            <input type="hidden" id="anchor_string" name="anchor_string" value="<?php echo $escape($anchor_string) ?>">
            <input type="hidden" name="app" value="<?php echo $escape($app) ?>">

            <?php echo $formFields ?>
            <?php echo $languageSelector ?>

            <div class="form-group">
                <button type="submit" id="login-button" class="btn btn-primary btn-block">Sign In</button>
            </div>

            <?php echo $passwordResetLink ?>
        </form>

<?php if (!empty($oauthProviders)): ?>
        <div class="login-separator" style="display:flex;align-items:center;margin:20px 0">
            <hr style="flex:1;border:none;border-top:1px solid #ddd">
            <span style="padding:0 12px;color:#888;font-size:0.9em"><?php echo _("or") ?></span>
            <hr style="flex:1;border:none;border-top:1px solid #ddd">
        </div>
        <div class="oauth-buttons">
<?php foreach ($oauthProviders as $provider): ?>
            <form method="post" action="<?php echo $escape($oauthLoginBaseUrl) ?>/<?php echo $escape($provider['provider_id']) ?>">
                <input type="hidden" name="url" value="<?php echo $escape($url) ?>">
                <button type="submit" class="btn btn-block" style="margin-bottom:8px;padding:10px 16px;border:1px solid #ccc;border-radius:4px;cursor:pointer;font-size:1em;width:100%<?php if (!empty($provider['display_color'])): ?>;background-color:<?php echo $escape($provider['display_color']) ?>;color:#fff;border-color:<?php echo $escape($provider['display_color']) ?><?php endif ?>">
                    <?php echo $escape($provider['display_label'] ?: $provider['name']) ?>
                </button>
            </form>
<?php endforeach ?>
        </div>
<?php endif ?>
    </div>

<script>
// Pass data to external JavaScript
window.HordeLoginPreSelected = <?php echo json_encode($vars->get('horde_select_view', $_COOKIE['default_horde_view'] ?? 'auto'), JSON_HEX_TAG | JSON_HEX_AMP) ?>;
window.HordeLoginStrings = {
    username: <?php echo json_encode(_("Please enter a username."), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    password: <?php echo json_encode(_("Please enter a password."), JSON_HEX_TAG | JSON_HEX_AMP) ?>,
    capsLock: <?php echo json_encode(_("Caps Lock is on"), JSON_HEX_TAG | JSON_HEX_AMP) ?>
};
</script>
<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
exit;

$page_output->footer();
