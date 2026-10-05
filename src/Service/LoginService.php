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
use Horde_Exception;
use Horde_Notification_Handler;
use Horde_Registry;
use Horde_Url;
use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\Config\RegistryState;
use Horde\Core\Service\OAuthProviderConfigRepository;
use Horde\Core\Service\PrefsService;
use Horde\Core\Session\HordeSession;
use Horde\Core\Session\SessionAccess;
use Horde\Core\Session\SessionConfig;
use Horde\Core\Session\SessionLifecycle;
use Horde\Token\Exception\TokenException;
use Horde\Token\Token;
use Horde\Horde\Auth\LoginReasonMapper;
use Horde\Horde\Auth\ModeOptionsBuilder;
use Horde\Horde\Login;
use Horde\Horde\View\LoginFormFieldRenderer;
use Horde\Horde\Factory\LoginServiceFactory;
use Horde\Horde\ValueObject\LoginAttempt;
use Horde\Horde\ValueObject\LoginFormData;
use Horde\Horde\ValueObject\LoginResult;
use Horde\Horde\ValueObject\LogoutRequest;
use Horde\Injector\Injector;
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
 * arrive as method arguments. Long-lived collaborators (registry, logger,
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
        private readonly SessionAccess $session,
        private readonly SessionLifecycle $sessionLifecycle,
        private readonly SessionConfig $sessionConfig,
        private readonly Horde_Notification_Handler $notification,
        private readonly PrefsService $prefsService,
        private readonly AppLoginParamCollector $appLoginParamCollector,
        private readonly array $conf,
    ) {}

    /**
     * Assemble all data required to render the login form.
     *
     * @param array<string, mixed> $queryParams  GET query parameters
     * @param array<string, string> $cookieParams Request cookies
     * @param ?string $formActionUrlOverride      Override the form POST target
     *                                            (default: $webroot/auth/login)
     */
    public function buildLoginFormData(
        array $queryParams,
        array $cookieParams,
        ?string $errorCode = null,
        ?int $logoutReason = null,
        ?string $logoutMsg = null,
        ?string $errorMessage = null,
        ?string $formActionUrlOverride = null,
    ): LoginFormData {

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

        // Also collect login params from every application that declares
        // the 'loginparams' auth capability, independently of the primary
        // Horde auth driver. This lets an app's login UI (e.g. IMP's mail
        // server selector) appear even when Horde authenticates via LDAP,
        // SQL or any other driver unrelated to that app.
        $appLoginParams = $this->appLoginParamCollector->collect();
        $loginparams = array_filter(array_merge($loginparams, $appLoginParams['params']));
        $jsCode = array_merge($jsCode, $appLoginParams['js_code']);
        $jsFiles = array_merge($jsFiles, $appLoginParams['js_files']);

        // Mode selector
        $modeSelector = '';
        if (!empty($this->conf['user']['select_view'])) {
            $modeSelector = $this->renderModeSelector($cookieParams);
        }

        // Language selector. Lock state is read through the modern
        // PrefsService, avoiding problems with uninitialized globals in modern routes.
        $languageSelector = '';
        $isGuest = !$this->registry->isAuthenticated();
        // Lock state is deployment-wide and ignores UID.
        $uid = $this->session->getAuthId() ?? '';
        if ($isGuest && !$this->prefsService->isLocked($uid, 'horde', 'language')) {
            // Honour a language chosen on the login screen itself (GET
            // ?new_lang=...), so the page re-renders in that language and
            // the matching option is pre-selected. Mirrors the pre-auth
            // language switch that legacy login.php performed. The POST
            // login path applies new_lang separately in attemptLogin().
            $newLang = $queryParams['new_lang'] ?? null;
            if (is_string($newLang) && $newLang !== '') {
                try {
                    $this->registry->setLanguageEnvironment($newLang);
                } catch (Throwable $e) {
                    // Ignore invalid/unknown language codes.
                }
            }
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

        // Show password login form?
        $showPasswordLogin = $this->conf['auth']['show_password_login'] ?? true;

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

        // Form action URL (caller may override for legacy entry points)
        $formActionUrl = $formActionUrlOverride ?? ($webroot . '/auth/login');

        // Render form fields HTML
        $renderer = new LoginFormFieldRenderer();
        $formFields = $renderer->renderAll($loginparams)['fields'];

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
            showPasswordLogin: $showPasswordLogin,
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

        // Password login administratively disabled instance-wide: reject any
        // posted username/password outright, even though the visible form
        // field was removed. Mirrors the same guard in login.php.
        $showPasswordLogin = ($this->conf['auth']['show_password_login'] ?? true) !== false;
        if (!$showPasswordLogin) {
            $this->auditService->logLoginFailure(
                $attempt->username,
                $attempt->app,
                $attempt->remoteAddr,
                $attempt->forwardedFor,
            );
            return new LoginResult(success: false, errorCode: 'badlogin');
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

            $errorCode = LoginReasonMapper::constantToErrorCode($auth->getError());

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

        // Stash per-app login-param selections in the session so an app's
        // transparent-auth logic can honor the user's selection (e.g. IMP's
        // mail-server choice) even though Horde itself authenticated via a
        // different driver (LDAP, SQL, ...). Companion of login.php's
        // `session->set('horde', 'login_app_params', ...)`.
        $appLoginParams = $this->appLoginParamCollector->collect(true, $attempt->backendParams);
        $appLoginSelection = array_filter($appLoginParams['posted']);
        if (!empty($appLoginSelection)) {
            $this->session->setScoped('horde', 'login_app_params', $appLoginSelection);
        }

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
        $sessionId = (string) $this->session->current()->getId();

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

        // Set language in the new session.
        // resolveCurrentLanguage(). Future cleanup: a typed
        // LanguageResolver service.
        $this->registry->setLanguage($this->resolveCurrentLanguage());

        // Anonymous-user preference reload was a legacy Horde_Prefs
        // concern (its session-cache driver needed an explicit retrieve()
        // after clearAuth). PrefsService is stateless with no per-request
        // cache, so there is nothing to reload.

        // Check redirect_on_logout config
        if ($request->reason === Horde_Auth::REASON_LOGOUT
            && !empty($this->conf['auth']['redirect_on_logout'])) {
            $logoutUrl = $this->conf['auth']['redirect_on_logout'];
            return ['redirect' => $logoutUrl, 'reason' => $request->reason];
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
     * @param array<string, string> $cookieParams Cookies from the request
     *                                            (PSR-7 `getCookieParams()`).
     */
    private function renderModeSelector(array $cookieParams): string
    {
        // Canonical mode options via shared builder (fixes former mobile_nojs divergence)
        $modes = ModeOptionsBuilder::build(
            includeBasic: !empty($this->conf['user']['select_basic_view']),
            includeMinimal: !empty($this->conf['user']['select_minimal_view']),
        );

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
        return LoginReasonMapper::constantToUserMessage($reason, $logoutMsg);
    }

    /**
     * @return array{string, string}
     */
    private function resolveErrorCodeMessage(string $errorCode, ?string $errorMessage = null): array
    {
        return LoginReasonMapper::errorCodeToUserMessage($errorCode, $errorMessage);
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
            $altUrl->add($sessionCookieName, (string) $this->session->current()->getId());
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
     * Resolve the active language for the language selector dropdown.
     *
     * Reads from the Nlsconfig service (the canonical source) instead of
     * the legacy `$GLOBALS['language']` mirror.
     */
    private function resolveCurrentLanguage(): string
    {
        return (string)$this->registry->nlsconfig->language;
    }
}
