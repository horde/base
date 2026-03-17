<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Horde\Core\Assets\ResponsiveAssets;
use Horde\Core\View\ResponsiveTemplateView;
use Horde\Horde\Login;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Exception;
use Horde;
use Horde_Variables;

/**
 * Responsive Login Controller
 *
 * Modern login screen using responsive design system.
 * Uses PSR-7/PSR-15 interfaces for modern request handling.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */
class ResponsiveLoginController implements RequestHandlerInterface
{
    use HtmlResponseTrait;
    use RedirectResponseTrait;
    /**
     * Handle the login request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Check if this is a POST (login attempt)
        if ($request->getMethod() === 'POST') {
            return $this->handleLoginPost($request);
        }

        // Otherwise show the login form
        return $this->showLoginForm($request);
    }

    /**
     * Show the login form
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function showLoginForm(ServerRequestInterface $request): ResponseInterface
    {
        // Get registry and injector from request attributes
        $registry = $request->getAttribute('registry');
        $injector = $GLOBALS['injector'] ?? null;
        $vars = $injector?->getInstance('Horde_Variables') ?? new Horde_Variables();

        // Get query params
        $queryParams = $request->getQueryParams();

        // Check authentication using PSR-15 attribute from AuthHordeSession middleware
        $authenticatedUser = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        $isGuest = $request->getAttribute('HORDE_GUEST');

        // If already authenticated with no query params, redirect to index.php
        // This handles the case where user manually navigates to /auth/login while logged in
        if ($authenticatedUser && empty($queryParams)) {
            $indexUrl = $registry->get('webroot', 'horde') . '/index.php';
            return $this->redirect($indexUrl);
        }

        // Get ResponsiveAssets helper
        $responsiveAssets = new ResponsiveAssets($registry);

        // Try to get prefs from injector first, fallback to global
        $prefs = null;
        try {
            if ($injector) {
                $prefs = $injector->getInstance('Horde_Core_Factory_Prefs')->create();
            }
        } catch (Exception $e) {
            $prefs = $GLOBALS['prefs'] ?? null;
        }

        // Get login handler
        $loginHandler = $injector?->getInstance(Login::class);

        // Get asset paths from registry
        $themesUri = $registry->get('themesuri', 'horde');
        $webroot = $registry->get('webroot', 'horde');

        // Build login parameters (includes second factor if enabled)
        $loginparams = $loginHandler ? $loginHandler->buildLoginParams() : [];

        // Check for error messages (from failed login attempts)
        $error = $queryParams['error'] ?? null;

        // Check for logout reason (e.g., password changed, session expired)
        $logoutReason = $queryParams['logout_reason'] ?? null;
        $logoutMsg = $queryParams['logout_msg'] ?? null;

        // Build language selector if not locked (only for guests)
        $langs = [];
        if ($isGuest) {
            // Check if language selection is locked
            $langLocked = false;
            if ($prefs) {
                try {
                    $langLocked = $prefs->isLocked('language');
                } catch (Exception $e) {
                    // Ignore - proceed without lock check
                }
            }

            if (!$langLocked) {
                // Get all configured languages and validate with setlocale
                try {
                    $availableLanguages = $registry->nlsconfig->languages ?? [];
                    $currentLang = $GLOBALS['language'] ?? 'en_US';

                    if (!empty($availableLanguages)) {
                        foreach ($availableLanguages as $key => $val) {
                            try {
                                if ($registry->nlsconfig->validLang($key)) {
                                    $langs[] = [
                                        'sel' => ($key == $currentLang),
                                        'val' => $key,
                                        'name' => $val,
                                    ];
                                }
                            } catch (Exception $e) {
                                // Skip languages that fail validation
                                continue;
                            }
                        }
                    }
                } catch (Exception $e) {
                    // If nlsconfig is not available, no languages added
                }

                // If no languages were found, add default English
                if (empty($langs)) {
                    $langs[] = [
                        'sel' => true,
                        'val' => 'en_US',
                        'name' => 'English (American)',
                    ];
                }
            }
        }

        // Check if mode selector should be shown
        // Get conf from global
        $conf = $GLOBALS['conf'] ?? [];

        // Default to true if config isn't available (show selector by default)
        $showModeSelector = !isset($conf['user']) || !empty($conf['user']['select_view'] ?? true);

        // Check if password reset is enabled
        $showPasswordReset = !empty($conf['auth']['resetpassword'] ?? false);

        // Generate form fields HTML
        $formFields = $this->renderFormFields($loginparams);
        $languageSelector = $this->renderLanguageSelector($langs);
        $modeSelector = $showModeSelector ? $this->renderModeSelector($vars) : '';
        $passwordResetLink = $showPasswordReset ? $this->renderPasswordResetLink($webroot) : '';
        $errorHtml = $this->renderError($error);
        $logoutMessageHtml = $this->renderLogoutMessage($logoutReason, $logoutMsg);

        // Build view data for template
        $viewData = [
            // Asset URLs from ResponsiveAssets helper
            'cssUrls' => $responsiveAssets->getCssUrls(),
            'jsUrls' => $responsiveAssets->getJsUrls(),

            // Theme info
            'theme' => $responsiveAssets->getTheme(),

            // Registry paths (for logo, etc.)
            'themesUri' => $themesUri,
            'webroot' => $webroot,

            // Pre-rendered HTML components
            'formFields' => $formFields,
            'languageSelector' => $languageSelector,
            'modeSelector' => $modeSelector,
            'passwordResetLink' => $passwordResetLink,
            'errorHtml' => $errorHtml,
            'logoutMessageHtml' => $logoutMessageHtml,

            // Query params for redirects - ensure they're strings, not arrays
            'app' => is_string($queryParams['app'] ?? null) ? $queryParams['app'] : 'horde',
            'url' => is_string($queryParams['url'] ?? null) ? $queryParams['url'] : '',
            'anchor_string' => is_string($queryParams['anchor_string'] ?? null) ? $queryParams['anchor_string'] : '',
        ];

        // Create view and render
        $templatePath = __DIR__ . '/../../templates/auth/login.html.php';
        $view = new ResponsiveTemplateView($templatePath, $viewData);

        // Use Horde\Http to create response
        $streamFactory = new StreamFactory();
        $response = new Response();
        $stream = $streamFactory->createStream($view->render());
        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(200);
    }

    /**
     * Render form fields from login parameters
     *
     * @param array $loginparams
     * @return string
     */
    private function renderFormFields(array $loginparams): string
    {
        $html = '';

        foreach ($loginparams as $key => $param) {
            $label = $param['label'] ?? '';
            $type = $param['type'] ?? 'text';
            $value = $param['value'] ?? '';
            $extra = $param['extra'] ?? [];

            // Build extra attributes
            $attrs = '';
            foreach ($extra as $attrKey => $attrVal) {
                $attrs .= ' ' . $this->escapeHtml($attrKey) . '="' . $this->escapeHtml($attrVal) . '"';
            }

            // Add default autocomplete attributes for text fields
            if ($type === 'text' && $key === 'horde_user') {
                $attrs .= ' autocomplete="username" autocapitalize="off" autocorrect="off"';
            } elseif ($type === 'password') {
                $attrs .= ' autocomplete="current-password"';
                $value = ''; // Never pre-fill passwords
            }

            $html .= <<<HTML
                            <div class="form-group">
                                <label for="{$this->escapeHtml($key)}" class="form-label">{$this->escapeHtml($label)}</label>
                                <input
                                    type="{$this->escapeHtml($type)}"
                                    id="{$this->escapeHtml($key)}"
                                    name="{$this->escapeHtml($key)}"
                                    class="form-input"
                                    value="{$this->escapeHtml($value)}"
                                    {$attrs}
                                    required
                                >
                            </div>

                HTML;
        }

        return $html;
    }

    /**
     * Render language selector
     *
     * @param array $langs
     * @return string
     */
    private function renderLanguageSelector(array $langs): string
    {
        if (empty($langs)) {
            return '';
        }

        $options = '';
        foreach ($langs as $lang) {
            $selected = $lang['sel'] ? ' selected="selected"' : '';
            $options .= '<option value="' . $this->escapeHtml($lang['val']) . '"' . $selected . '>'
                      . $lang['name'] . '</option>' . "\n";
        }

        return <<<HTML
                        <div class="form-group">
                            <label for="new_lang" class="form-label">Language</label>
                            <select id="new_lang" name="new_lang" class="form-input">
                                {$options}
                            </select>
                        </div>

            HTML;
    }

    /**
     * Render mode selector
     *
     * @param Horde_Variables $vars
     * @return string
     */
    private function renderModeSelector($vars): string
    {
        $conf = $GLOBALS['conf'] ?? [];
        $currentMode = $vars->get('horde_select_view', $_COOKIE['default_horde_view'] ?? 'auto');

        // Start with automatic mode
        $modes = [
            'auto' => _("Automatic"),
        ];

        // Add Basic mode if explicitly enabled (default: disabled)
        if (!empty($conf['user']['select_basic_view'])) {
            $modes['basic'] = _("Basic");
        }

        // Always include Dynamic mode
        $modes['dynamic'] = _("Dynamic");

        // Add Minimal mode if explicitly enabled (default: disabled)
        if (!empty($conf['user']['select_minimal_view'])) {
            $modes['mobile'] = _("Mobile (Minimal)");
        }

        // Always include Smartmobile/Responsive mode
        $modes['smartmobile'] = _("Mobile (Smartphone/Tablet)");

        $options = '';
        foreach ($modes as $value => $name) {
            $selected = ($value === $currentMode) ? ' selected="selected"' : '';
            $options .= '<option value="' . $this->escapeHtml($value) . '"' . $selected . '>'
                      . $this->escapeHtml($name) . '</option>' . "\n";
        }

        return <<<HTML
                        <div class="form-group">
                            <label for="horde_select_view" class="form-label">{$this->escapeHtml(_("Mode"))}</label>
                            <select id="horde_select_view" name="horde_select_view" class="form-input">
                                {$options}
                            </select>
                        </div>

            HTML;
    }

    /**
     * Render error message if present
     *
     * @param string|null $error
     * @return string
     */
    private function renderError(?string $error): string
    {
        if (empty($error)) {
            return '';
        }

        $messages = [
            'failed' => 'Login failed. Please check your username and password.',
            'logout' => 'You have been logged out.',
            'session' => 'Your session has expired. Please log in again.',
            'expired' => 'Your login has expired.',
            'badlogin' => 'Login failed because your username or password was entered incorrectly.',
            'secondfactor' => 'Second factor authentication failed. Please check your authentication code and try again.',
        ];

        $message = $messages[$error] ?? 'An error occurred. Please try again.';

        // Check for custom second factor error message
        if ($error === 'secondfactor' && !empty($_GET['msg'])) {
            $customMsg = $_GET['msg'];
            // Message is already from the 2FA provider, just escape it
            $message = htmlspecialchars($customMsg, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }

        $alertClass = ($error === 'logout') ? 'alert-info' : 'alert-error';

        return <<<HTML
                    <div class="alert {$alertClass}">
                        {$this->escapeHtml($message)}
                    </div>

            HTML;
    }

    /**
     * Render logout message if present
     *
     * @param int|null $reason Logout reason constant
     * @param string|null $message Custom logout message
     * @return string
     */
    private function renderLogoutMessage(?int $reason, ?string $message): string
    {
        if (empty($reason)) {
            return '';
        }

        // Determine alert class based on reason
        // REASON_MESSAGE (5) = informational (password changed, etc.)
        // REASON_LOGOUT (4) = normal logout
        // Others = warnings/errors
        $alertClass = 'alert-info';
        if ($reason === 5) { // REASON_MESSAGE
            $alertClass = 'alert-success'; // Password changed successfully
        }

        $displayMessage = $message ? htmlspecialchars($message, ENT_QUOTES | ENT_HTML5, 'UTF-8') : 'You have been logged out.';

        return <<<HTML
                    <div class="alert {$alertClass}">
                        {$displayMessage}
                    </div>

            HTML;
    }

    /**
     * Render password reset link
     *
     * @param string $webroot
     * @return string
     */
    private function renderPasswordResetLink(string $webroot): string
    {
        return <<<HTML
                        <div class="login-help">
                            <a href="{$webroot}/login.php?url=&horde_pass_reset=1" class="text-muted">
                                Forgot your password?
                            </a>
                        </div>

            HTML;
    }

    /**
     * Handle login form POST
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    private function handleLoginPost(ServerRequestInterface $request): ResponseInterface
    {
        $registry = $request->getAttribute('registry');
        $injector = $GLOBALS['injector'] ?? null;
        $webroot = $registry->get('webroot', 'horde');

        // Get form data - check both getParsedBody and POST
        $body = $request->getParsedBody() ?? $_POST ?? [];

        $username = trim($body['horde_user'] ?? '');
        $password = $body['horde_pass'] ?? '';
        $secondFactor = $body['horde_secondfactor'] ?? '';
        $redirectUrl = $body['url'] ?? '';
        $app = $body['app'] ?? 'horde';

        // Get view mode selection from form
        $selectView = $body['horde_select_view'] ?? null;
        if ($selectView === 'mobile_nojs') {
            $selectView = 'mobile';
        }

        // Handle language change
        if (!empty($body['new_lang'])) {
            try {
                $registry->setLanguageEnvironment($body['new_lang']);
            } catch (Exception $e) {
                // Ignore language change errors
            }
        }

        // Validate credentials
        if (empty($username) || empty($password)) {
            return $this->redirectToLoginPage($webroot, '?error=badlogin');
        }

        // Validate second factor if enabled
        $loginHandler = $injector?->getInstance(Login::class);
        if ($loginHandler && $loginHandler->secondFactorSupported()) {
            try {
                $message = $loginHandler->secondFactorApi('blockLogin', 'Second factor API error', [
                    $username,
                    $secondFactor,
                ]);

                // If message returned, 2FA validation failed
                if ($message) {
                    return $this->redirectToLoginPage($webroot, '?error=secondfactor&msg=' . urlencode($message));
                }
            } catch (Exception $e) {
                // 2FA validation failed (exception or not configured for user)
                return $this->redirectToLoginPage($webroot, '?error=secondfactor');
            }
        }

        // Authenticate using AuthenticationService
        try {
            $authService = $injector?->getInstance(AuthenticationService::class);
            if (!$authService) {
                // Fallback: create service manually
                $authService = new AuthenticationService($registry, null);
            }

            // Build authentication options
            $authOptions = ['generate_jwt' => true];
            if ($selectView !== null) {
                $authOptions['mode'] = $selectView;
            }

            $result = $authService->authenticate($username, $password, $authOptions);

            if (!$result['success']) {
                return $this->redirectToLoginPage($webroot, '?error=badlogin');
            }

            // DEBUG: Log what we got back
            Horde::log('LOGIN RESULT: ' . json_encode([
                'success' => $result['success'],
                'has_access_token' => isset($result['access_token']),
                'has_refresh_token' => isset($result['refresh_token']),
                'session_id' => $result['session_id'] ?? 'NONE',
            ]), 'DEBUG');

            // Authentication successful
            // If JWT tokens were generated, store refresh token in cookie and pass to JS
            $response = new Response();

            if (isset($result['access_token']) && isset($result['refresh_token'])) {
                // Extract JTI from refresh token
                $parts = explode('.', $result['refresh_token']);
                if (count($parts) === 3) {
                    try {
                        $payload = json_decode(
                            base64_decode(strtr($parts[1], '-_', '+/')),
                            true,
                            512,
                            JSON_THROW_ON_ERROR
                        );
                        $jti = $payload['jti'] ?? null;

                        if ($jti) {
                            // Migrate session data to JTI-based session
                            $oldSessionId = session_id();
                            Horde::log("LOGIN: Migrating session from $oldSessionId to JTI: $jti", 'DEBUG');

                            // Save current session data
                            $sessionData = $_SESSION;

                            // Close and destroy old session
                            session_write_close();

                            // Start new session with JTI as ID
                            session_id($jti);
                            session_start();

                            // Restore session data
                            $_SESSION = $sessionData;

                            Horde::log("LOGIN: Session migrated to JTI: $jti", 'DEBUG');
                        }
                    } catch (Exception $e) {
                        Horde::log("LOGIN: Failed to extract JTI for session migration: " . $e->getMessage(), 'WARN');
                    }
                }

                // Set refresh token as HTTP-only cookie for middleware to use
                // This allows JwtSession middleware to use JTI as session ID on next request
                $conf = $GLOBALS['conf'] ?? [];
                $response = $response->withHeader('Set-Cookie', sprintf(
                    'horde_jwt_refresh=%s; Path=%s; HttpOnly; SameSite=Strict%s',
                    urlencode($result['refresh_token']),
                    $conf['cookie']['path'] ?? '/',
                    (!empty($conf['use_ssl']) ? '; Secure' : '')
                ));

                // Pass tokens via session flash that JS can read once for localStorage
                $_SESSION['__horde']['jwt_bootstrap'] = [
                    'access_token' => $result['access_token'],
                    'refresh_token' => $result['refresh_token'],
                    'expires_at' => $result['expires_at'],
                ];
            }

            // Redirect to requested URL or portal
            if (!empty($redirectUrl)) {
                $location = $redirectUrl;
            } else {
                // Use registry to get the correct portal link based on user's view preference
                // This handles desktop vs mobile mode correctly
                $location = $registry->getServiceLink('portal');
            }

            return $response
                ->withStatus(302)
                ->withHeader('Location', $location);

        } catch (Exception $e) {
            return $this->redirectToLoginPage($webroot, '?error=failed');
        }
    }

    /**
     * Redirect to login page with error
     *
     * @param string $webroot Webroot path from registry
     * @param string $query Query string (e.g., "?error=badlogin")
     * @return ResponseInterface
     */
    private function redirectToLoginPage(string $webroot, string $query = ''): ResponseInterface
    {
        return $this->redirect($webroot . '/auth/login' . $query);
    }
}
