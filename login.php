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
    Horde_Registry::appInit('horde', [
        'authentication' => 'none',
        'nologintasks' => true,
    ]);
} catch (Horde_Exception_AuthenticationFailure $e) {
}

$is_auth = $registry->isAuthenticated();
$vars = $injector->getInstance('Horde_Variables');
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

    /* Reload preferences as anonymous/guest user to get default/global theme. */
    try {
        $prefs = $injector->getInstance('Horde_Prefs');
        // Force prefs to reload for anonymous user (cleared by clearAuth above)
        $prefs->retrieve();
    } catch (Horde_Exception $e) {
        // Ignore - theme will use system default
    }
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
    $auth_params = [
        'password' => Horde_Util::getPost('horde_pass'),
        'mode' => $select_view,
    ];

    try {
        $result = $auth->getLoginParams();
        foreach (array_keys($result['params']) as $val) {
            $auth_params[$val] = Horde_Util::getPost($val);
        }
    } catch (Horde_Exception $e) {
    }

    // TODO: Factor out into login handler class
    // First check if we need to validate the second factor.
    $authUser = Horde_Util::getPost('horde_user') ?? '';
    $errorSecondFactor = false;
    if ($loginHandler->secondFactorMode > 0) {
        $message = null;
        try {
            $authSecondFactor = (string) Horde_Util::getPost('horde_secondfactor');
            $message = $registry->call('secondfactor/blockLogin', [
                $authUser,
                $authSecondFactor,
            ]);
            if ($message) {
                $errorSecondFactor = Horde_Auth::REASON_MESSAGE;
            }
        } catch (Horde_Exception $e) {
            $errorSecondFactor = Horde_Auth::REASON_BADLOGIN;
        }

        if ($errorSecondFactor) {
            $auth->setError($errorSecondFactor, $message);
        }
    }

    if (!$errorSecondFactor && $auth->authenticate($authUser, $auth_params)) {
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
    $loginparams['horde_select_view'] = [
        'type'   => 'select',
        'label'  => _("Mode"),
        'value'  => [
            'auto'        => [ 'name' => _("Automatic") ],
            'disabled'    => null,
            'basic'       => [ 'name' => _("Basic") ],
            'dynamic'     => [ 'name' => _("Dynamic") ],
            'smartmobile' => [ 'name' => _("Mobile (Smartphone/Tablet)") ],
            'mobile'      => [ 'name' => _("Mobile (Minimal)") ],
            'mobile_nojs' => [ 'name' => _("Mobile (No JavaScript)") ],
        ],
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

/* If we currently are authenticated, and are not trying to authenticate to
 * an application, redirect to initial page. This is done in index.php.
 * If we are trying to authenticate to an application, but don't have to,
 * redirect to the requesting URL. */
if ($is_auth) {
    if (!$vars->app) {
        require HORDE_BASE . '/index.php';
        exit;
    } elseif ($url_in &&
              $registry->isAuthenticated(['app' => $vars->app])) {
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
$responsiveAssets = new \Horde\Core\Assets\ResponsiveAssets($registry);

// Get webroot and themes URI
$webroot = $registry->get('webroot', 'horde');
$themesUri = $registry->get('themesuri', 'horde');
$jsUri = $registry->get('jsuri', 'horde');
$theme = $responsiveAssets->getTheme();
$cssUrls = $responsiveAssets->getCssUrls();

// Build JS URLs from $js_files array
$jsUrls = [];
// First add Prototype.js which login.js depends on
$jsUrls[] = $jsUri . '/prototype.js';
// Then add the login-specific files
foreach ($js_files as $jsFile) {
    if (is_array($jsFile)) {
        list($file, $app) = $jsFile;
        $jsUrls[] = $registry->get('jsuri', $app) . '/' . $file;
    } else {
        $jsUrls[] = $jsUri . '/' . $jsFile;
    }
}

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
                $safeKey = is_scalar($optKey) ? (string)$optKey : '';
                $safeName = is_scalar($optVal['name'] ?? null) ? (string)($optVal['name'] ?? $safeKey) : $safeKey;
                $formFields .= '<option value="' . htmlspecialchars($safeKey, ENT_QUOTES) . '"' . $selected . '>' . htmlspecialchars($safeName, ENT_QUOTES) . '</option>';
            }
        }
        $formFields .= '</select></div>';
    } elseif ($type === 'text' || $type === 'password') {
        $inputType = $type;
        // Ensure value is string - arrays should not be used for text/password fields
        $stringValue = is_array($value) ? '' : (string)$value;

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

        $formFields .= '<div class="form-group"' . $divAttrs . '>';
        $formFields .= '<label for="' . htmlspecialchars($key, ENT_QUOTES) . '" class="form-label">' . htmlspecialchars($label, ENT_QUOTES) . '</label>';
        $formFields .= '<input type="' . $inputType . '" id="' . htmlspecialchars($key, ENT_QUOTES) . '" name="' . htmlspecialchars($key, ENT_QUOTES) . '" class="form-input" value="' . $inputValue . '" />';
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
        $languageSelector .= '<option value="' . htmlspecialchars($lang['val'], ENT_QUOTES) . '"' . $selected . '>' .
            $lang['name'] . '</option>';
    }
    $languageSelector .= '</select></div>';
}

$passwordResetLink = '';
$app = $vars->app ?? 'horde';
$url = $vars->url ?? '';
$anchor_string = $vars->anchor_string ?? '';

// Simple escape function for the template
$escape = function($str) {
    return htmlspecialchars($str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
<body class="login-page">
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
    </div>

<script>
// Inline JS variables for login.js
<?php foreach ($js_code as $key => $value): ?>
<?php echo $key ?> = <?php echo json_encode($value, JSON_HEX_TAG | JSON_HEX_AMP) ?>;
<?php endforeach; ?>
</script>
<?php foreach ($jsUrls as $jsUrl): ?>
    <script src="<?php echo $escape($jsUrl) ?>"></script>
<?php endforeach; ?>
</body>
</html>
<?php
exit;

$page_output->footer();
