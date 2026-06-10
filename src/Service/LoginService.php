<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\Service;

use Exception;
use Horde;
use Horde_Auth;
use Horde_Core_Auth_Application;
use Horde_Exception;
use Horde_Registry;
use Horde_Url;
use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Config\RegistryState;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Session\HordeSession;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token;
use Horde\Horde\Login;
use Horde\Horde\Factory\LoginServiceFactory;
use Horde\Horde\ValueObject\LoginAttempt;
use Horde\Horde\ValueObject\LoginFormData;
use Horde\Horde\ValueObject\LoginResult;
use Horde\Horde\ValueObject\LogoutRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Horde\Injector\Attribute\Factory;
use Throwable;

/**
 * Orchestrates the full login/logout lifecycle.
 *
 * Shared by both the UI controller (ResponsiveLoginController) and
 * potentially login.php in a future migration.
 */
#[Factory(factory: LoginServiceFactory::class, method: 'create')]
class LoginService
{
    public function __construct(
        private readonly Horde_Registry $registry,
        private readonly LoggerInterface $logger,
        private readonly Login $loginFormBuilder,
        private readonly RedirectValidationService $redirectValidator,
        private readonly AuditService $auditService,
        private readonly AuthenticationService $authService,
        private readonly OAuthProviderConfigRepository $providerConfig,
        private readonly array $conf,
    ) {}

    /**
     * Assemble all data required to render the login form.
     */
    public function buildLoginFormData(
        ServerRequestInterface $request,
        ?string $errorCode = null,
        ?int $logoutReason = null,
        ?string $logoutMsg = null,
        ?string $errorMessage = null,
    ): LoginFormData {
        $queryParams = $request->getQueryParams();
        $injector = $GLOBALS['injector'] ?? null;

        $webroot = $this->registry->get('webroot', 'horde');
        $themesUri = $this->registry->get('themesuri', 'horde');

        // Responsive asset helper
        $responsiveAssets = new ResponsiveAssets(
            new RegistryState($this->registry->applications),
        );
        $theme = $responsiveAssets->getTheme();
        $cssUrls = $responsiveAssets->getCssUrls('horde');
        $jsUrls = $responsiveAssets->getJsUrls('horde');

        // Base login params from Login service (username, password, 2FA)
        $loginparams = $this->loginFormBuilder->buildLoginParams();

        // Auth backend params (additional fields, JS)
        $jsCode = [];
        $jsFiles = [];
        try {
            $auth = $injector->getInstance('Horde_Core_Factory_Auth')->create();
            $result = $auth->getLoginParams();
            $loginparams = array_filter(array_merge($loginparams, $result['params']));
            $jsCode = $result['js_code'] ?? [];
            $jsFiles = $result['js_files'] ?? [];
        } catch (Horde_Exception $e) {
            // No backend-specific params
        }

        // Mode selector
        $modeSelector = '';
        if (!empty($this->conf['user']['select_view'])) {
            $modeSelector = $this->renderModeSelector();
        }

        // Language selector
        $languageSelector = '';
        $isGuest = !$this->registry->isAuthenticated();
        $prefs = $GLOBALS['prefs'] ?? null;
        if ($isGuest && $prefs && !$prefs->isLocked('language')) {
            $languageSelector = $this->renderLanguageSelector();
        }

        // Error/logout messages
        $errorHtml = $this->renderErrorMessage($errorCode, $logoutReason, $logoutMsg, $errorMessage);

        // OAuth providers
        $oauthProviders = [];
        if (!empty($this->conf['oauth_login']['enabled'])) {
            try {
                foreach ($this->providerConfig->listEnabled() as $provider) {
                    if (!empty($provider['client_id']) && ($provider['type'] ?? '') !== 'service_app') {
                        $oauthProviders[] = $provider;
                    }
                }
            } catch (Exception $e) {
                // Silently skip
            }
        }

        // Password reset link
        $passwordResetLink = '';
        if (!empty($this->conf['auth']['resetpassword'])) {
            $passwordResetLink = $this->renderPasswordResetLink($webroot);
        }

        // Alternate login redirect
        $alternateLoginUrl = null;
        if (!empty($this->conf['auth']['alternate_login'])) {
            $alternateLoginUrl = $this->buildAlternateLoginUrl(
                $this->conf['auth']['alternate_login'],
                $queryParams['app'] ?? '',
                $queryParams['url'] ?? '',
                $queryParams['anchor_string'] ?? '',
            );
        }

        // Form action URL (force SSL if configured)
        $formActionUrl = $webroot . '/auth/login';

        // Render form fields HTML
        $formFields = $this->renderFormFields($loginparams);

        // Query param sanitization
        $app = is_string($queryParams['app'] ?? null) ? $queryParams['app'] : 'horde';
        $url = is_string($queryParams['url'] ?? null) ? $queryParams['url'] : '';
        $anchorString = is_string($queryParams['anchor_string'] ?? null)
            ? $queryParams['anchor_string'] : '';

        return new LoginFormData(
            formFields: $formFields,
            languageSelector: $languageSelector,
            modeSelector: $modeSelector,
            passwordResetLink: $passwordResetLink,
            errorHtml: $errorHtml,
            jsCode: $jsCode,
            jsFiles: $jsFiles,
            oauthProviders: $oauthProviders,
            oauthLoginBaseUrl: $webroot . '/auth/oauth/login',
            formActionUrl: $formActionUrl,
            alternateLoginUrl: $alternateLoginUrl,
            app: $app,
            url: $url,
            anchorString: $anchorString,
            themesUri: $themesUri,
            theme: $theme,
            cssUrls: $cssUrls,
            jsUrls: $jsUrls,
        );
    }

    /**
     * Execute a login attempt and return the result.
     */
    public function attemptLogin(LoginAttempt $attempt): LoginResult
    {
        $injector = $GLOBALS['injector'] ?? null;

        // Empty credentials check
        if (empty($attempt->username) || empty($attempt->password)) {
            return new LoginResult(success: false, errorCode: 'required');
        }

        // Language change
        if (!empty($attempt->newLang)) {
            try {
                $this->registry->setLanguageEnvironment($attempt->newLang);
            } catch (Throwable $e) {
                // Ignore language change errors (including ValueError on empty textdomain)
            }
        }

        // Get auth driver (app-specific if requested)
        $isAppAuth = !empty($attempt->app)
            && $this->registry->isAuthenticated();
        $auth = $injector->getInstance('Horde_Core_Factory_Auth')
            ->create($isAppAuth ? $attempt->app : null);

        // Build credentials including backend-specific params
        $authCredentials = ['password' => $attempt->password];
        if (!empty($attempt->selectView)) {
            $nojs = false;
            $mode = $attempt->selectView;
            if ($mode === 'mobile_nojs') {
                $nojs = true;
                $mode = 'mobile';
            }
            $authCredentials['mode'] = $mode;
        }

        // Collect backend-specific params
        try {
            $backendResult = $auth->getLoginParams();
            foreach (array_keys($backendResult['params'] ?? []) as $paramKey) {
                if (isset($attempt->backendParams[$paramKey])) {
                    $authCredentials[$paramKey] = $attempt->backendParams[$paramKey];
                }
            }
        } catch (Horde_Exception $e) {
            // No backend params
        }

        // Second factor validation
        if ($this->loginFormBuilder->secondFactorSupported()) {
            try {
                $message = $this->loginFormBuilder->secondFactorApi(
                    'blockLogin',
                    'Second factor API error',
                    [$attempt->username, $attempt->secondFactor ?? ''],
                );
                if ($message) {
                    $auth->setError(Horde_Auth::REASON_MESSAGE, $message);
                    $this->auditService->logLoginFailure(
                        $attempt->username,
                        $attempt->app,
                        $attempt->remoteAddr,
                        $attempt->forwardedFor,
                    );
                    return new LoginResult(
                        success: false,
                        errorCode: 'secondfactor',
                        errorMessage: $message,
                    );
                }
            } catch (Horde_Exception $e) {
                $this->auditService->logLoginFailure(
                    $attempt->username,
                    $attempt->app,
                    $attempt->remoteAddr,
                    $attempt->forwardedFor,
                );
                return new LoginResult(success: false, errorCode: 'secondfactor');
            }
        }

        // Authenticate
        if (!$auth->authenticate($attempt->username, $authCredentials)) {
            $this->auditService->logLoginFailure(
                $attempt->username,
                $attempt->app,
                $attempt->remoteAddr,
                $attempt->forwardedFor,
            );

            $errorCode = match ($auth->getError()) {
                Horde_Auth::REASON_BADLOGIN => 'badlogin',
                Horde_Auth::REASON_EXPIRED => 'expired',
                Horde_Auth::REASON_LOCKED => 'locked',
                Horde_Auth::REASON_MESSAGE => 'secondfactor',
                default => 'failed',
            };

            return new LoginResult(
                success: false,
                errorCode: $errorCode,
                errorMessage: $auth->getError(true) ?: null,
            );
        }

        // Authentication succeeded
        $this->auditService->logLoginSuccess(
            $this->registry->getAuth(),
            $isAppAuth ? $attempt->app : null,
            $attempt->remoteAddr,
            $attempt->forwardedFor,
        );

        // Mobile no-JS notification
        if (!$isAppAuth && ($nojs ?? false)) {
            $GLOBALS['notification']->push(
                _("JavaScript is either disabled or not available. You are restricted to the minimal view."),
                'horde.message',
            );
        }

        // Check password change requirement
        $passwordChangeRequired = !$isAppAuth && $this->registry->passwordChangeRequested();
        $hasUpdateCapability = $auth->hasCapability('update');

        // Resolve post-login redirect
        $redirectUrl = null;
        if (!empty($attempt->redirectUrl)) {
            $verified = $this->redirectValidator->verifySignedUrl($attempt->redirectUrl);
            if ($verified) {
                $validated = $this->redirectValidator->validateRedirectUrl($verified);
                if ($validated) {
                    $redirectUrl = $this->redirectValidator->addAnchor(
                        $validated,
                        $attempt->anchorString,
                    );
                }
            } else {
                $validated = $this->redirectValidator->validateRedirectUrl($attempt->redirectUrl);
                if ($validated) {
                    $redirectUrl = $this->redirectValidator->addAnchor(
                        $validated,
                        $attempt->anchorString,
                    );
                }
            }
        }

        if ($redirectUrl === null) {
            $redirectUrl = $this->redirectValidator->resolveInitialPage();
        }

        // Generate JWT tokens if available
        $accessToken = null;
        $refreshToken = null;
        $expiresAt = null;
        $sessionId = null;

        if ($this->authService->hasJwtSupport()) {
            try {
                $jwtResult = $this->authService->issueTokensForAuthenticatedUser(
                    $this->registry->getAuth(),
                    ['generate_jwt' => true],
                );
                $accessToken = $jwtResult['access_token'] ?? null;
                $refreshToken = $jwtResult['refresh_token'] ?? null;
                $expiresAt = $jwtResult['expires_at'] ?? null;
                $sessionId = session_id();
            } catch (Exception $e) {
                $this->logger->debug('JWT token generation skipped: ' . $e->getMessage());
                $sessionId = session_id();
            }
        } else {
            $sessionId = session_id();
        }

        return new LoginResult(
            success: true,
            userId: $this->registry->getAuth(),
            passwordChangeRequired: $passwordChangeRequired,
            hasUpdateCapability: $hasUpdateCapability,
            redirectUrl: $redirectUrl,
            sessionId: $sessionId,
            accessToken: $accessToken,
            refreshToken: $refreshToken,
            expiresAt: $expiresAt,
        );
    }

    /**
     * Execute the logout ceremony.
     *
     * @return array{redirect: string, reason: int}
     * @throws Horde_Exception If CSRF token is invalid.
     */
    public function performLogout(LogoutRequest $request): array
    {
        $notification = $GLOBALS['notification'] ?? null;

        // CSRF verification
        if (!empty($request->csrfToken)) {
            $tokenService = $GLOBALS['injector']->getInstance(Token::class);
            try {
                $valid = $tokenService->isValid($request->csrfToken, HordeSession::CSRF_SEED);
            } catch (TokenException $e) {
                throw new Horde_Exception('Invalid token!');
            }
            if (!$valid) {
                throw new Horde_Exception('Invalid token!');
            }
        }

        // Pulled later for the lifecycle setup() call below — that site is
        // still on the shim because HordeSession lacks a lifecycle surface.
        $session = $GLOBALS['session'] ?? null;

        // Audit log
        $currentUser = $this->registry->getAuth();
        if ($currentUser) {
            $this->auditService->logLogout(
                $currentUser,
                $request->remoteAddr,
                $request->forwardedFor,
            );
        }

        // Clear authentication
        $this->registry->clearAuth();

        // Reset notification handler (old handler may reference invalid state)
        if ($notification) {
            $notification->detach('status');
            $notification->attach('status');
        }

        // Check redirect_on_logout config
        if ($request->reason === Horde_Auth::REASON_LOGOUT
            && !empty($this->conf['auth']['redirect_on_logout'])) {
            $logoutUrl = $this->conf['auth']['redirect_on_logout'];
            return ['redirect' => $logoutUrl, 'reason' => $request->reason];
        }

        // Setup fresh anonymous session
        if ($session) {
            $session->setup();
        }

        // Set language in the new session
        $this->registry->setLanguage($GLOBALS['language'] ?? 'en_US');

        // Reload preferences for anonymous user
        try {
            $prefs = $GLOBALS['injector']->getInstance('Horde_Prefs');
            $prefs->retrieve();
        } catch (Exception $e) {
            // Ignore - theme will use system default
        }

        // Default redirect to login page
        $webroot = $this->registry->get('webroot', 'horde');
        $redirect = $webroot . '/auth/login?logout_reason=logout';

        if (!empty($request->redirectUrl)) {
            $validated = $this->redirectValidator->validateRedirectUrl($request->redirectUrl);
            if ($validated) {
                $redirect = $validated;
            }
        }

        return ['redirect' => $redirect, 'reason' => $request->reason];
    }

    /**
     * Render form fields HTML including extra attributes.
     */
    private function renderFormFields(array $loginparams): string
    {
        $html = '';

        foreach ($loginparams as $key => $param) {
            if ($key === 'new_lang') {
                continue;
            }

            $label = $param['label'] ?? ucfirst($key);
            $type = $param['type'] ?? 'text';
            $value = $param['value'] ?? '';
            $extra = $param['extra'] ?? [];

            // Build div wrapper attributes
            $divAttrs = '';
            if (isset($param['div'])) {
                foreach ($param['div'] as $attr => $attrValue) {
                    $divAttrs .= ' ' . $this->escape($attr) . '="' . $this->escape($attrValue) . '"';
                }
            }

            if ($type === 'select') {
                $html .= $this->renderSelectField($key, $label, $param['value'] ?? [], $divAttrs);
            } elseif ($type === 'text' || $type === 'password') {
                // Default extras for text fields
                if (empty($extra) && $type === 'text') {
                    $extra = ['autocapitalize' => 'off', 'autocorrect' => 'off'];
                }

                // Username-specific defaults
                if ($key === 'horde_user' && !isset($extra['autocomplete'])) {
                    $extra['autocomplete'] = 'username';
                }

                // Password defaults
                if ($type === 'password' && !isset($extra['autocomplete'])) {
                    $extra['autocomplete'] = 'current-password';
                }

                // Build extra attribute string
                $extraAttrs = '';
                foreach ($extra as $attrKey => $attrVal) {
                    $extraAttrs .= ' ' . $this->escape($attrKey) . '="' . $this->escape($attrVal) . '"';
                }

                // Value handling
                $inputValue = '';
                if ($type !== 'password') {
                    $inputValue = $this->escape(is_array($value) ? '' : (string) $value);
                }

                $html .= '<div class="form-group"' . $divAttrs . '>';
                $html .= '<label for="' . $this->escape($key) . '" class="form-label">'
                    . $this->escape($label) . '</label>';
                $html .= '<input type="' . $this->escape($type) . '" id="' . $this->escape($key)
                    . '" name="' . $this->escape($key) . '" class="form-input" value="'
                    . $inputValue . '"' . $extraAttrs . ' required>';
                $html .= '</div>';
            }
        }

        return $html;
    }

    private function renderSelectField(string $key, string $label, array $options, string $divAttrs): string
    {
        $html = '<div class="form-group"' . $divAttrs . '>';
        $html .= '<label for="' . $this->escape($key) . '" class="form-label">'
            . $this->escape($label) . '</label>';
        $html .= '<select id="' . $this->escape($key) . '" name="' . $this->escape($key)
            . '" class="form-input">';

        foreach ($options as $optKey => $optVal) {
            if ($optVal === null) {
                continue;
            }
            if (is_array($optVal)) {
                $selected = !empty($optVal['selected']) ? ' selected' : '';
                $safeKey = is_scalar($optKey) ? (string) $optKey : '';
                $safeName = is_scalar($optVal['name'] ?? null) ? (string) ($optVal['name'] ?? $safeKey) : $safeKey;
                $html .= '<option value="' . $this->escape($safeKey) . '"' . $selected . '>'
                    . $this->escape($safeName) . '</option>';
            }
        }

        $html .= '</select></div>';
        return $html;
    }

    private function renderModeSelector(): string
    {
        $modes = ['auto' => _("Automatic")];

        if (!empty($this->conf['user']['select_basic_view'])) {
            $modes['basic'] = _("Basic");
        }

        $modes['dynamic'] = _("Dynamic");

        if (!empty($this->conf['user']['select_minimal_view'])) {
            $modes['mobile'] = _("Mobile (Minimal)");
        }

        $modes['smartmobile'] = _("Mobile (Smartphone/Tablet)");

        $currentMode = $_COOKIE['default_horde_view'] ?? 'auto';

        $options = '';
        foreach ($modes as $value => $name) {
            $selected = ($value === $currentMode) ? ' selected' : '';
            $options .= '<option value="' . $this->escape($value) . '"' . $selected . '>'
                . $this->escape($name) . '</option>';
        }

        return '<div class="form-group">'
            . '<label for="horde_select_view" class="form-label">' . $this->escape(_("Mode")) . '</label>'
            . '<select id="horde_select_view" name="horde_select_view" class="form-input">'
            . $options . '</select></div>';
    }

    private function renderLanguageSelector(): string
    {
        $langs = [];
        $currentLang = $GLOBALS['language'] ?? 'en_US';

        try {
            foreach ($this->registry->nlsconfig->languages as $key => $val) {
                if ($this->registry->nlsconfig->validLang($key)) {
                    $langs[] = ['sel' => ($key == $currentLang), 'val' => $key, 'name' => $val];
                }
            }
        } catch (Exception $e) {
            return '';
        }

        if (empty($langs)) {
            return '';
        }

        $options = '';
        foreach ($langs as $lang) {
            $selected = $lang['sel'] ? ' selected' : '';
            // Language names are already HTML-encoded
            $options .= '<option value="' . $this->escape($lang['val']) . '"' . $selected . '>'
                . $lang['name'] . '</option>';
        }

        return '<div class="form-group">'
            . '<label for="new_lang" class="form-label">' . $this->escape(_("Language")) . '</label>'
            . '<select id="new_lang" name="new_lang" class="form-input">'
            . $options . '</select></div>';
    }

    private function renderErrorMessage(
        ?string $errorCode,
        ?int $logoutReason,
        ?string $logoutMsg,
        ?string $errorMessage = null,
    ): string {
        // Resolve message from logout reason constants
        $message = null;
        $alertClass = 'alert-error';

        if ($logoutReason !== null) {
            [$message, $alertClass] = $this->resolveLogoutReasonMessage($logoutReason, $logoutMsg);
        } elseif ($errorCode !== null) {
            [$message, $alertClass] = $this->resolveErrorCodeMessage($errorCode, $errorMessage);
        }

        if ($message === null) {
            return '';
        }

        return '<div class="alert ' . $alertClass . '">'
            . $this->escape($message) . '</div>';
    }

    /**
     * @return array{string, string}
     */
    private function resolveLogoutReasonMessage(int $reason, ?string $logoutMsg): array
    {
        $alertClass = 'alert-error';

        $message = match ($reason) {
            Horde_Auth::REASON_SESSION => _("Your session has expired. Please login again."),
            Horde_Core_Auth_Application::REASON_SESSIONIP => _("Your Internet Address has changed since the beginning of your session. To protect your security, you must login again."),
            Horde_Core_Auth_Application::REASON_BROWSER => _("Your browser appears to have changed since the beginning of your session. To protect your security, you must login again."),
            Horde_Core_Auth_Application::REASON_SESSIONMAXTIME => _("Your session length has exceeded the maximum amount of time allowed. Please login again."),
            Horde_Auth::REASON_LOGOUT => _("You have been logged out."),
            Horde_Auth::REASON_FAILED => _("Login failed."),
            Horde_Auth::REASON_BADLOGIN => _("Login failed because your username or password was entered incorrectly."),
            Horde_Auth::REASON_EXPIRED => _("Your login has expired."),
            Horde_Auth::REASON_LOCKED, Horde_Auth::REASON_MESSAGE => $logoutMsg ?? _("Login failed."),
            default => null,
        };

        if ($message === null) {
            return [_("Login failed."), 'alert-error'];
        }

        $alertClass = match ($reason) {
            Horde_Auth::REASON_LOGOUT => 'alert-info',
            Horde_Auth::REASON_SESSION,
            Horde_Core_Auth_Application::REASON_SESSIONIP,
            Horde_Core_Auth_Application::REASON_BROWSER,
            Horde_Core_Auth_Application::REASON_SESSIONMAXTIME => 'alert-info',
            Horde_Auth::REASON_MESSAGE => 'alert-success',
            default => 'alert-error',
        };

        return [$message, $alertClass];
    }

    /**
     * @return array{string, string}
     */
    private function resolveErrorCodeMessage(string $errorCode, ?string $errorMessage = null): array
    {
        $message = match ($errorCode) {
            'badlogin' => _("Login failed because your username or password was entered incorrectly."),
            'expired' => _("Your login has expired."),
            'locked' => _("Your account has been locked."),
            'required' => _("Please enter a username and password."),
            'secondfactor' => $errorMessage ?? _("Second factor authentication failed."),
            'failed' => _("Login failed."),
            'logout' => _("You have been logged out."),
            default => _("An error occurred. Please try again."),
        };

        $alertClass = ($errorCode === 'logout') ? 'alert-info' : 'alert-error';

        return [$message, $alertClass];
    }

    private function renderPasswordResetLink(string $webroot): string
    {
        return '<div class="login-help"><a href="'
            . $this->escape($webroot)
            . '/services/resetpassword.php" class="text-muted">'
            . $this->escape(_("Forgot your password?"))
            . '</a></div>';
    }

    private function buildAlternateLoginUrl(
        string $alternateLogin,
        string $app,
        string $url,
        string $anchorString,
    ): string {
        $altUrl = new Horde_Url($alternateLogin, true);

        if (!empty($app)) {
            $altUrl->add('app', $app);
        }

        if (!isset($_COOKIE[session_name()])) {
            $altUrl->add(session_name(), session_id());
        }

        if (!empty($url)) {
            $altUrl->add('url', $url);
        }

        return (string) $altUrl;
    }

    private function escape(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
