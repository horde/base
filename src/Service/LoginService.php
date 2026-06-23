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
use Horde_Notification_Handler;
use Horde_Registry;
use Horde_Url;
use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Config\RegistryState;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\PreLogoutHandlerInterface;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionConfig;
use Horde\Core\Session\SessionLifecycle;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token;
use Horde\Horde\Login;
use Horde\Horde\Factory\LoginServiceFactory;
use Horde\Horde\ValueObject\LoginAttempt;
use Horde\Horde\ValueObject\LoginFormData;
use Horde\Horde\ValueObject\LoginResult;
use Horde\Horde\ValueObject\LogoutRequest;
use Horde\Injector\Injector;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use Horde\Injector\Attribute\Factory;
use Throwable;

/**
 * Orchestrates the full login/logout lifecycle.
 *
 * Shared by both the UI controller (ResponsiveLoginController) and
 * potentially login.php in a future migration.
 *
 * Reads no globals directly. Per-request collaborators (request, cookies)
 * arrive as method arguments; long-lived collaborators (registry, logger,
 * session, lifecycle, notification handler, prefs binding) arrive via the
 * constructor through {@see LoginServiceFactory}.
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
        private readonly Injector $injector,
        private readonly HordeSession $session,
        private readonly SessionLifecycle $sessionLifecycle,
        private readonly SessionConfig $sessionConfig,
        private readonly Horde_Notification_Handler $notification,
        private readonly array $conf,
        private readonly array $preLogoutHandlers = [],
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
        $cookieParams = $request->getCookieParams();

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
            $auth = $this->injector->getInstance('Horde_Core_Factory_Auth')->create();
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
            $modeSelector = $this->renderModeSelector($cookieParams);
        }

        // Language selector. Active-user prefs are an injector-resolved
        // resource set up by Registry::setupSessionHandler at auth time.
        // Resolving lazily keeps LoginService usable in pre-auth contexts
        // where 'Horde_Prefs' is not yet bound.
        $languageSelector = '';
        $isGuest = !$this->registry->isAuthenticated();
        $prefs = $this->resolvePrefs();
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
                $cookieParams,
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
        $auth = $this->injector->getInstance('Horde_Core_Factory_Auth')
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
            $this->notification->push(
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
        $sessionId = (string) $this->session->getId();

        if ($this->authService->hasJwtSupport()) {
            try {
                $jwtResult = $this->authService->issueTokensForAuthenticatedUser(
                    $this->registry->getAuth(),
                    ['generate_jwt' => true],
                );
                $accessToken = $jwtResult['access_token'] ?? null;
                $refreshToken = $jwtResult['refresh_token'] ?? null;
                $expiresAt = $jwtResult['expires_at'] ?? null;
            } catch (Exception $e) {
                $this->logger->debug('JWT token generation skipped: ' . $e->getMessage());
            }
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
        // CSRF verification
        if (!empty($request->csrfToken)) {
            $tokenService = $this->injector->getInstance(Token::class);
            try {
                $valid = $tokenService->isValid($request->csrfToken, HordeSession::CSRF_SEED);
            } catch (TokenException $e) {
                throw new Horde_Exception('Invalid token!');
            }
            if (!$valid) {
                throw new Horde_Exception('Invalid token!');
            }
        }

        // Audit log
        $currentUser = $this->registry->getAuth();
        if ($currentUser) {
            $this->auditService->logLogout(
                $currentUser,
                $request->remoteAddr,
                $request->forwardedFor,
            );
        }

        // Run pre-logout handlers before the session is destroyed.
        // A failing handler must never prevent the logout from completing.
        $postLogoutRedirect = null;
        if ($currentUser) {
            foreach ($this->preLogoutHandlers as $handler) {
                try {
                    $data = $handler->onBeforeLogout($currentUser, $request->reason);
                    if (!empty($data['redirect']) && $postLogoutRedirect === null) {
                        $postLogoutRedirect = $data['redirect'];
                    }
                } catch (Throwable $e) {
                    $this->logger->warning(
                        'PreLogoutHandler ' . $handler::class . ' failed: ' . $e->getMessage()
                    );
                }
            }
        }

        // Clear authentication
        $this->registry->clearAuth();

        // Reset notification handler (old handler may reference invalid state)
        $this->notification->detach('status');
        $this->notification->attach('status');

        // Setup fresh anonymous session via the modern lifecycle.
        // SessionLifecycle::setup() is idempotent and replaces the
        // legacy shim's $GLOBALS['session']->setup() path that this
        // service used to walk.
        $this->sessionLifecycle->setup();

        // Set language in the new session. The translation env was set
        // up by the Registry bootstrap to whatever $GLOBALS['language']
        // had become; honour that for backwards compatibility through
        // resolveCurrentLanguage(). Future cleanup: a typed
        // LanguageResolver service.
        $this->registry->setLanguage($this->resolveCurrentLanguage());

        // Reload preferences for anonymous user. 'Horde_Prefs' is bound
        // by Registry::setupSessionHandler at auth time; resolving
        // through the injector keeps the per-request binding live.
        try {
            $prefs = $this->injector->getInstance('Horde_Prefs');
            $prefs->retrieve();
        } catch (Exception $e) {
            // Ignore - theme will use system default
        }

        // Handler redirect takes priority over redirect_on_logout config
        if ($postLogoutRedirect === null
            && $request->reason === Horde_Auth::REASON_LOGOUT
            && !empty($this->conf['auth']['redirect_on_logout'])) {
            $postLogoutRedirect = $this->conf['auth']['redirect_on_logout'];
        }

        if ($postLogoutRedirect !== null) {
            return ['redirect' => $postLogoutRedirect, 'reason' => $request->reason];
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

    /**
     * @param array<string, string> $cookieParams Cookies from the request
     *                                            (PSR-7 `getCookieParams()`).
     */
    private function renderModeSelector(array $cookieParams): string
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

        $currentMode = $cookieParams['default_horde_view'] ?? 'auto';

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
        $currentLang = $this->resolveCurrentLanguage();

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

    /**
     * @param array<string, string> $cookieParams Cookies from the request
     *                                            (PSR-7 `getCookieParams()`).
     */
    private function buildAlternateLoginUrl(
        string $alternateLogin,
        string $app,
        string $url,
        string $anchorString,
        array $cookieParams,
    ): string {
        $altUrl = new Horde_Url($alternateLogin, true);

        if (!empty($app)) {
            $altUrl->add('app', $app);
        }

        // The session cookie is named by SessionConfig. When the
        // request did not arrive with it, propagate the current id via
        // query so the alternate login host can resume the same
        // session.
        $sessionCookieName = $this->sessionConfig->cookieName;
        if ($sessionCookieName !== '' && !isset($cookieParams[$sessionCookieName])) {
            $altUrl->add($sessionCookieName, (string) $this->session->getId());
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

    /**
     * Resolve the active-user `Horde_Prefs` from the injector, or null if
     * no per-user prefs are bound (anonymous request or pre-auth context).
     *
     * Registry::setupSessionHandler binds 'Horde_Prefs' on each request
     * once the user is identified. A guest request without that binding
     * gets null here, which the caller treats as "no language selector".
     */
    private function resolvePrefs(): ?\Horde_Prefs
    {
        try {
            $prefs = $this->injector->getInstance('Horde_Prefs');
        } catch (Throwable) {
            return null;
        }
        return $prefs instanceof \Horde_Prefs ? $prefs : null;
    }

    /**
     * Resolve the active language for the language selector dropdown.
     *
     * Reads the per-request `$GLOBALS['language']` set by the legacy
     * Registry bootstrap. A typed LanguageResolver service belongs in
     * the same Gap-10 cleanup that owns prefs reload and login-tasks
     * decoupling; honouring the global here keeps current behaviour
     * stable until that arrives.
     */
    private function resolveCurrentLanguage(): string
    {
        $language = $GLOBALS['language'] ?? null;
        return is_string($language) && $language !== '' ? $language : 'en_US';
    }
}
