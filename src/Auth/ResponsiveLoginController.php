<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Horde\Horde\Login;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde\Http\Response;
use Horde\Http\StreamFactory;

/**
 * Responsive Login Controller
 *
 * Modern login screen using responsive design system.
 * Uses PSR-7/PSR-15 interfaces for modern request handling.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * @author   Claude Code Assistant
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */
class ResponsiveLoginController implements RequestHandlerInterface
{
    /**
     * Handle the login request
     *
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        // Get registry and injector from request attributes
        $registry = $request->getAttribute('registry');
        $injector = $GLOBALS['injector'] ?? null;
        $vars = $injector?->getInstance('Horde_Variables') ?? new \Horde_Variables();

        // Try to get prefs from injector first, fallback to global
        $prefs = null;
        try {
            if ($injector) {
                $prefs = $injector->getInstance('Horde_Core_Factory_Prefs')->create();
            }
        } catch (\Exception $e) {
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
        $error = $request->getQueryParams()['error'] ?? null;

        // Check if user is already authenticated
        $is_auth = $registry->isAuthenticated();

        // Build language selector if not locked
        $langs = [];
        $debug = "is_auth=$is_auth, prefs=" . (isset($prefs) ? 'yes' : 'no');
        if (!$is_auth) {
            // Check if language selection is locked
            $langLocked = false;
            if ($prefs) {
                try {
                    $langLocked = $prefs->isLocked('language');
                } catch (\Exception $e) {
                    // Ignore - proceed without lock check
                }
            }
            $debug .= ", langLocked=$langLocked";

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
                            } catch (\Exception $e) {
                                // Skip languages that fail validation
                                continue;
                            }
                        }
                    }
                } catch (\Exception $e) {
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
        // Get conf from global or default to false
        $conf = $GLOBALS['conf'] ?? [];
        $showModeSelector = !empty($conf['user']['select_view'] ?? false);

        // Check if password reset is enabled
        $showPasswordReset = !empty($conf['auth']['resetpassword'] ?? false);

        // Generate form fields HTML
        $formFields = $this->renderFormFields($loginparams);
        $languageSelector = $this->renderLanguageSelector($langs);
        $modeSelector = $showModeSelector ? $this->renderModeSelector($vars) : '';
        $passwordResetLink = $showPasswordReset ? $this->renderPasswordResetLink($webroot) : '';

        // Create HTML body
        $html = <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Horde</title>
    <link rel="stylesheet" href="{$themesUri}/default/responsive.css">
</head>
<body class="login-page">
    <div class="login-card card">
        <div class="login-logo">
            <img src="{$themesUri}/default/graphics/logo.png" alt="Horde">
        </div>

        <div class="card-header">
            <h1 class="card-title">Welcome to Horde</h1>
            <p class="card-subtitle">Sign in to continue</p>
            <!-- DEBUG: {$debug} langs=" . count($langs) . " -->
        </div>

        {$this->renderError($error)}

        <form method="post" action="{$webroot}/login.php" id="login-form">
            <input type="hidden" name="login_post" value="1">
            <input type="hidden" name="url" value="{$this->escapeHtml($vars->url ?? '')}">
            <input type="hidden" name="anchor_string" value="{$this->escapeHtml($vars->anchor_string ?? '')}">
            <input type="hidden" name="app" value="{$this->escapeHtml($vars->app ?? '')}">

            {$formFields}
            {$languageSelector}
            {$modeSelector}

            <button type="submit" class="btn btn-primary btn-block">
                Sign In
            </button>

            {$passwordResetLink}
        </form>

        <div class="login-footer">
            <p>&copy; 2026 <a href="https://www.horde.org/">Horde LLC</a></p>
        </div>
    </div>

    <script>
        // Capture redirect URL from hash or query params if not already set
        const urlInput = document.querySelector('input[name="url"]');
        const anchorInput = document.querySelector('input[name="anchor_string"]');

        if (!urlInput.value) {
            const params = new URLSearchParams(window.location.search);
            const returnUrl = params.get('url');
            if (returnUrl) {
                urlInput.value = returnUrl;
            }
        }

        if (!anchorInput.value && window.location.hash) {
            anchorInput.value = window.location.hash.substring(1);
        }

        // Focus first input field
        const firstInput = document.querySelector('input[type="text"], input[type="password"]');
        if (firstInput) {
            firstInput.focus();
        }
    </script>
</body>
</html>
HTML;

        // Use Horde\Http to create response
        $streamFactory = new StreamFactory();
        $response = new Response();
        $stream = $streamFactory->createStream($html);
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
     * @param \Horde_Variables $vars
     * @return string
     */
    private function renderModeSelector($vars): string
    {
        $currentMode = $vars->get('horde_select_view', $_COOKIE['default_horde_view'] ?? 'auto');

        $modes = [
            'auto' => 'Automatic',
            'basic' => 'Basic',
            'dynamic' => 'Dynamic',
            'smartmobile' => 'Mobile (Smartphone/Tablet)',
        ];

        $options = '';
        foreach ($modes as $value => $name) {
            $selected = ($value === $currentMode) ? ' selected="selected"' : '';
            $options .= '<option value="' . $this->escapeHtml($value) . '"' . $selected . '>'
                      . $this->escapeHtml($name) . '</option>' . "\n";
        }

        return <<<HTML
            <div class="form-group">
                <label for="horde_select_view" class="form-label">Mode</label>
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
        ];

        $message = $messages[$error] ?? 'An error occurred. Please try again.';
        $alertClass = ($error === 'logout') ? 'alert-info' : 'alert-error';

        return <<<HTML
        <div class="alert {$alertClass}">
            {$this->escapeHtml($message)}
        </div>

HTML;
    }

    /**
     * Escape HTML entities
     *
     * @param string $text
     * @return string
     */
    private function escapeHtml(string $text): string
    {
        return htmlspecialchars($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
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
}
