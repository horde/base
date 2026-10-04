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
use Horde\Util\Util;
use Horde\Util\Variables;
use Horde\Horde\Auth\LoginReasonMapper;
use Horde\Horde\HordeConfig;
use Horde\Horde\Service\LoginService;
use Horde\Horde\Service\RedirectValidationService;
use Horde\Horde\ValueObject\LoginAttempt;
use Horde\Horde\ValueObject\LogoutRequest;

require_once __DIR__ . '/lib/Application.php';
try {
    Horde_Registry::appInit('horde', [
        'authentication' => 'none',
        'nologintasks' => true,
    ]);
} catch (Horde_Exception_AuthenticationFailure $e) {
}

$is_auth = $registry->isAuthenticated();
$vars    = $injector->getInstance(Variables::class);
$config  = $injector->get(HordeConfig::class);
$auth    = $injector->getInstance('Horde_Core_Factory_Auth')
    ->create(($is_auth && $vars->app) ? $vars->app : null);
$webroot = $registry->get('webroot', 'horde');

/* Get URL/Anchor strings now. */
if ($url_in = Horde::verifySignedUrl($vars->url)) {
    $url_in = new Horde_Url($url_in);
    $url_anchor = $url_in->anchor;
    $url_in->anchor = '';
} else {
    $url_anchor = $url_in = null;
}

/* Resolve error / logout reason from query params or auth driver. */
if (!($logout_reason = $auth->getError())) {
    $logout_reason = $vars->logout_reason;
}
if (!$logout_reason && $vars->error) {
    $logout_reason = LoginReasonMapper::stringToConstant($vars->error);
}
if ($logout_reason && is_string($logout_reason)) {
    $logout_reason = LoginReasonMapper::stringToConstant($logout_reason);
}

/* Logout handling */

if ($logout_reason && $is_auth) {
    $loginService = $injector->getInstance(LoginService::class);

    $logoutRequest = new LogoutRequest(
        csrfToken:    (string) $vars->horde_logout_token,
        reason:       $logout_reason,
        message:      is_string($vars->logout_msg) ? $vars->logout_msg : null,
        redirectUrl:  is_string($vars->url) ? $vars->url : null,
        anchorString: is_string($vars->anchor_string) ? $vars->anchor_string : null,
        remoteAddr:   (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        forwardedFor: !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : null,
    );

    try {
        $logoutResult = $loginService->performLogout($logoutRequest);
    } catch (Horde_Exception $e) {
        /* Invalid CSRF token, redirect to index. */
        $notification->push($e, 'horde.error');
        header('Location: ' . $webroot . '/index.php');
        exit;
    }

    header('Location: ' . $logoutResult['redirect']);
    exit;
}

/* POST login handling */

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $loginService = $injector->getInstance(LoginService::class);

    /* Collect backend-specific POST fields (everything not in our known set). */
    $knownFields = [
        'horde_user', 'horde_pass', 'horde_secondfactor',
        'horde_select_view', 'url', 'anchor_string', 'app', 'new_lang',
    ];
    $backendParams = [];
    foreach ($_POST as $key => $value) {
        if (!in_array($key, $knownFields, true)) {
            $backendParams[$key] = $value;
        }
    }

    $attempt = new LoginAttempt(
        username:     trim((string) Util::getPost('horde_user')),
        password:     (string) Util::getPost('horde_pass'),
        secondFactor: Util::getPost('horde_secondfactor') !== null
            ? (string) Util::getPost('horde_secondfactor') : null,
        selectView:   Util::getPost('horde_select_view') !== null
            ? (string) Util::getPost('horde_select_view') : null,
        app:          is_string($vars->app) ? $vars->app : null,
        redirectUrl:  is_string($vars->url) ? $vars->url : null,
        anchorString: is_string($vars->anchor_string) ? $vars->anchor_string : null,
        newLang:      Util::getPost('new_lang') !== null
            ? (string) Util::getPost('new_lang') : null,
        backendParams: $backendParams,
        remoteAddr:   (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
        forwardedFor: !empty($_SERVER['HTTP_X_FORWARDED_FOR'])
            ? (string) $_SERVER['HTTP_X_FORWARDED_FOR'] : null,
    );

    $loginResult = $loginService->attemptLogin($attempt);

    if ($loginResult->success) {
        /* JWT cookie. Same pattern as ResponsiveLoginController. */
        if ($loginResult->refreshToken !== null) {
            // TODO: Dubious: Setting the JWT refresh token as a cookie here may have security implications.
            header(sprintf(
                'Set-Cookie: horde_jwt_refresh=%s; Path=%s; HttpOnly; SameSite=Strict%s',
                urlencode($loginResult->refreshToken),
                $config->get('cookie.path') ?? '/',
                $config->get('use_ssl') ? '; Secure' : '',
            ), false);

            /* Session flash for JS to read once. */
            if ($loginResult->accessToken !== null) {
                $injector->getInstance(HordeSession::class)
                    ->setScoped('horde', 'jwt_bootstrap', [
                        'access_token'  => $loginResult->accessToken,
                        'refresh_token' => $loginResult->refreshToken,
                        'expires_at'    => $loginResult->expiresAt,
                    ]);
            }
        }

        /* Password change required. */
        if ($loginResult->passwordChangeRequired && $loginResult->hasUpdateCapability) {
            $notification->push(_("Your password has expired."), 'horde.message');
            header('Location: ' . $webroot . '/services/changepassword.php');
            exit;
        }

        /* Normal success. Redirect to post-login page. */
        $location = $loginResult->redirectUrl
            ?? $injector->getInstance(RedirectValidationService::class)
                ->resolveInitialPage();
        header('Location: ' . $location);
        exit;
    }

    /* Authentication failed. Redirect with preserved username + message. */
    $redirect = $webroot . '/login.php?error=' . urlencode($loginResult->errorCode ?? 'failed');
    if (!empty($attempt->username)) {
        $redirect .= '&user=' . urlencode($attempt->username);
    }
    if (!empty($loginResult->errorMessage)) {
        $redirect .= '&msg=' . urlencode($loginResult->errorMessage);
    }
    if (!empty($vars->url) && is_string($vars->url)) {
        $redirect .= '&url=' . urlencode($vars->url);
    }
    if (!empty($vars->app) && is_string($vars->app)) {
        $redirect .= '&app=' . urlencode($vars->app);
    }
    header('Location: ' . $redirect);
    exit;
}

if ($is_auth) {
    if (!$vars->app) {
        $location = $injector->getInstance(RedirectValidationService::class)
            ->resolveInitialPage();
        header('Location: ' . $location);
        exit;
    } elseif ($url_in
              && $registry->isAuthenticated(['app' => $vars->app])) {
        if ($url_anchor) {
            $url_in->anchor = $url_anchor;
        }
        $url_in->redirect();
    }
}

/* GET form rendering */

$loginService = $injector->getInstance(LoginService::class);

$backendMsg = $auth->getError(true)
    ?: (is_string($vars->logout_msg) ? $vars->logout_msg : null);

$formData = $loginService->buildLoginFormData(
    queryParams:          (array) ($_GET ?? []),
    cookieParams:         (array) ($_COOKIE ?? []),
    logoutReason:         $logout_reason ?: null,
    logoutMsg:            $backendMsg ?: null,
    formActionUrlOverride: $webroot . '/login.php',
);

/* Redirect the user if an alternate login page has been specified. */
if ($formData->alternateLoginUrl !== null) {
    (new Horde_Url($formData->alternateLoginUrl, true))->redirect();
}

/* Push error to notification handler */
if ($logout_reason) {
    [$reason] = LoginReasonMapper::constantToUserMessage(
        $logout_reason,
        $backendMsg ?: null,
    );
    if ($reason) {
        $notification->push(str_replace('<br />', ' ', $reason), 'horde.message');
    }
}

/* MOTD is trusted admin HTML and is echoed raw by design (see config/motd.php). */
$motdHtml = $injector->getInstance(Horde\Core\Config\MotdLoader::class)->load();

/* Template variables */

$escape = static function ($str) {
    if (is_array($str)) {
        return '';
    }
    return htmlspecialchars((string) $str, ENT_QUOTES | ENT_HTML5, 'UTF-8');
};

// Populated by LoginService::buildLoginFormData()
$cssUrls           = $formData->cssUrls;
$jsUrls            = $formData->jsUrls;
$theme             = $formData->theme;
$themesUri         = $formData->themesUri;
$formFields        = $formData->formFields;
$languageSelector  = $formData->languageSelector;
$modeSelector      = $formData->modeSelector;
$passwordResetLink = $formData->passwordResetLink;
$showPasswordLogin = $formData->showPasswordLogin;
$errorHtml         = $formData->errorHtml;
$oauthProviders    = $formData->oauthProviders;
$oauthLoginBaseUrl = $formData->oauthLoginBaseUrl;
$app               = $formData->app;
$url               = $formData->url;
$anchor_string     = $formData->anchorString;
$formActionUrl     = $formData->formActionUrl;

// login.php-specific template flags
$formId            = 'horde_login';
$extraHiddenFields = [];
$showFooter        = false;

// Merge service JS code with login.php-specific inline JS config.
$jsCode = array_merge($formData->jsCode, [
    'HordeLoginPreSelected' => $_GET['horde_select_view']
        ?? $_COOKIE['default_horde_view'] ?? 'auto',
    'HordeLoginStrings' => [
        'username' => _("Please enter a username."),
        'password' => _("Please enter a password."),
        'capsLock' => _("Caps Lock is on"),
    ],
]);
$jsFiles = $formData->jsFiles;

require __DIR__ . '/templates/login/responsive.html.php';
exit;
