<?php

declare(strict_types=1);

namespace Horde\Horde\Auth;

use Exception;
use Horde\Core\Config\RegistryConfigLoader;
use Horde\Horde\Service\LoginService;
use Horde\Horde\Service\RedirectValidationService;
use Horde\Horde\Traits\HtmlResponseTrait;
use Horde\Horde\Traits\RedirectResponseTrait;
use Horde\Horde\ValueObject\LoginAttempt;
use Horde\Core\View\ResponsiveTemplateView;
use Horde\Http\Response;
use Horde\Http\StreamFactory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Horde_Auth;

/**
 * Responsive Login Controller
 *
 * Thin PSR-15 controller that delegates login logic to LoginService.
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

    public function __construct(
        private readonly LoginService $loginService,
        private readonly RedirectValidationService $redirectValidator,
        private readonly RegistryConfigLoader $registryConfigLoader,
    ) {}

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        if ($request->getMethod() === 'POST') {
            return $this->handlePost($request);
        }
        return $this->showForm($request);
    }

    private function showForm(ServerRequestInterface $request): ResponseInterface
    {
        $registryState = $this->registryConfigLoader->load();
        $queryParams = $request->getQueryParams();

        // If already authenticated with no specific app/url, redirect to portal
        $authenticatedUser = $request->getAttribute('HORDE_AUTHENTICATED_USER');
        if ($authenticatedUser && empty($queryParams['app']) && empty($queryParams['url'])) {
            $location = $this->redirectValidator->resolveInitialPage();
            return $this->redirect($location);
        }

        // Build form data via LoginService
        $errorCode = is_string($queryParams['error'] ?? null) ? $queryParams['error'] : null;
        $errorMessage = is_string($queryParams['msg'] ?? null) ? $queryParams['msg'] : null;
        $logoutReason = isset($queryParams['logout_reason'])
            ? $this->mapLogoutReasonString($queryParams['logout_reason'])
            : null;
        $logoutMsg = is_string($queryParams['logout_msg'] ?? null) ? $queryParams['logout_msg'] : null;

        $formData = $this->loginService->buildLoginFormData(
            $request,
            $errorCode,
            $logoutReason,
            $logoutMsg,
            $errorMessage,
        );

        // If alternate_login is configured, redirect there
        if ($formData->alternateLoginUrl !== null) {
            return $this->redirect($formData->alternateLoginUrl);
        }

        // Build view data for template
        $viewData = [
            'cssUrls' => $formData->cssUrls,
            'jsUrls' => $formData->jsUrls,
            'jsCode' => $formData->jsCode,
            'jsFiles' => $formData->jsFiles,
            'theme' => $formData->theme,
            'themesUri' => $formData->themesUri,
            'webroot' => $registryState->getApplication('horde')['webroot'] ?? '',
            'formFields' => $formData->formFields,
            'languageSelector' => $formData->languageSelector,
            'modeSelector' => $formData->modeSelector,
            'passwordResetLink' => $formData->passwordResetLink,
            'errorHtml' => $formData->errorHtml,
            'oauthProviders' => $formData->oauthProviders,
            'oauthLoginBaseUrl' => $formData->oauthLoginBaseUrl,
            'app' => $formData->app,
            'url' => $formData->url,
            'anchor_string' => $formData->anchorString,
            'formActionUrl' => $formData->formActionUrl,
            'preservedUsername' => is_string($queryParams['user'] ?? null) ? $queryParams['user'] : '',
        ];

        // Render template
        $templatePath = __DIR__ . '/../../templates/auth/login.html.php';
        $view = new ResponsiveTemplateView($templatePath, $viewData);

        $streamFactory = new StreamFactory();
        $response = new Response();
        $stream = $streamFactory->createStream($view->render());
        return $response
            ->withBody($stream)
            ->withHeader('Content-Type', 'text/html; charset=utf-8')
            ->withStatus(200);
    }

    private function handlePost(ServerRequestInterface $request): ResponseInterface
    {
        $registryState = $this->registryConfigLoader->load();
        $webroot = $registryState->getApplication('horde')['webroot'] ?? '';
        $body = $request->getParsedBody() ?? $_POST ?? [];
        $serverParams = $request->getServerParams();

        // Collect backend-specific params (all POST fields not in our known set)
        $knownFields = [
            'horde_user', 'horde_pass', 'horde_secondfactor',
            'horde_select_view', 'url', 'anchor_string', 'app', 'new_lang',
            'login_post', 'login_button',
        ];
        $backendParams = [];
        foreach ($body as $key => $value) {
            if (!in_array($key, $knownFields, true)) {
                $backendParams[$key] = $value;
            }
        }

        $attempt = new LoginAttempt(
            username: trim((string) ($body['horde_user'] ?? '')),
            password: (string) ($body['horde_pass'] ?? ''),
            secondFactor: isset($body['horde_secondfactor'])
                ? (string) $body['horde_secondfactor'] : null,
            selectView: isset($body['horde_select_view'])
                ? (string) $body['horde_select_view'] : null,
            app: is_string($body['app'] ?? null) ? $body['app'] : null,
            redirectUrl: is_string($body['url'] ?? null) ? $body['url'] : null,
            anchorString: is_string($body['anchor_string'] ?? null) ? $body['anchor_string'] : null,
            newLang: isset($body['new_lang']) ? (string) $body['new_lang'] : null,
            backendParams: $backendParams,
            remoteAddr: (string) ($serverParams['REMOTE_ADDR'] ?? ''),
            forwardedFor: !empty($serverParams['HTTP_X_FORWARDED_FOR'])
                ? (string) $serverParams['HTTP_X_FORWARDED_FOR'] : null,
        );

        $result = $this->loginService->attemptLogin($attempt);

        if (!$result->success) {
            // PRG redirect with error code and preserved username
            $redirectUrl = $webroot . '/auth/login?error=' . urlencode($result->errorCode ?? 'failed');
            if (!empty($attempt->username)) {
                $redirectUrl .= '&user=' . urlencode($attempt->username);
            }
            if (!empty($result->errorMessage)) {
                $redirectUrl .= '&msg=' . urlencode($result->errorMessage);
            }
            if (!empty($attempt->redirectUrl)) {
                $redirectUrl .= '&url=' . urlencode($attempt->redirectUrl);
            }
            if (!empty($attempt->app)) {
                $redirectUrl .= '&app=' . urlencode($attempt->app);
            }
            return $this->redirect($redirectUrl);
        }

        // Password change required
        if ($result->passwordChangeRequired && $result->hasUpdateCapability) {
            $GLOBALS['notification']->push(
                _("Your password has expired."),
                'horde.message',
            );
            return $this->redirect($webroot . '/services/changepassword.php');
        }

        // Build response with optional JWT cookie
        $response = new Response();

        if ($result->refreshToken !== null) {
            $conf = $GLOBALS['conf'] ?? [];
            $response = $response->withHeader('Set-Cookie', sprintf(
                'horde_jwt_refresh=%s; Path=%s; HttpOnly; SameSite=Strict%s',
                urlencode($result->refreshToken),
                $conf['cookie']['path'] ?? '/',
                (!empty($conf['use_ssl']) ? '; Secure' : ''),
            ));

            // Store tokens in session flash for JS to read once
            if ($result->accessToken !== null) {
                $_SESSION['__horde']['jwt_bootstrap'] = [
                    'access_token' => $result->accessToken,
                    'refresh_token' => $result->refreshToken,
                    'expires_at' => $result->expiresAt,
                ];
            }
        }

        // Redirect to the post-login page
        $location = $result->redirectUrl
            ?? $this->redirectValidator->resolveInitialPage();

        return $response
            ->withStatus(302)
            ->withHeader('Location', $location);
    }

    private function mapLogoutReasonString(?string $reason): ?int
    {
        if ($reason === null) {
            return null;
        }

        if (is_numeric($reason)) {
            return (int) $reason;
        }

        return match ($reason) {
            'logout' => Horde_Auth::REASON_LOGOUT,
            'badlogin' => Horde_Auth::REASON_BADLOGIN,
            'expired' => Horde_Auth::REASON_EXPIRED,
            'locked' => Horde_Auth::REASON_LOCKED,
            'failed' => Horde_Auth::REASON_FAILED,
            'message' => Horde_Auth::REASON_MESSAGE,
            'session' => Horde_Auth::REASON_SESSION,
            default => null,
        };
    }
}
