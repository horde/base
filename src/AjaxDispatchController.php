<?php

declare(strict_types=1);

/**
 * PSR-15 controller for AJAX dispatch.
 *
 * Replicates the logic of services/ajax.php as a RequestHandler:
 * extract app and action from route params, bootstrap the app via
 * appInit for legacy handler support, create the Ajax Application
 * via factory, dispatch doAction(), drain notifications, and build
 * the HordeCore response envelope as a PSR-7 response.
 *
 * services/ajax.php is NOT modified — this controller is an
 * alternative entry point reachable when traffic goes through the
 * PSR-15 router.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @package   Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde;

use Horde\Core\Ajax\HordeCoreEnvelopeBuilder;
use Horde\Core\Factory\AjaxApplicationFactory;
use Horde\Util\Variables;
use Horde_Core_Ajax_Application;
use Horde_Core_Ajax_Response;
use Horde_Core_Ajax_Response_HordeCore;
use Horde_Exception;
use Horde_Exception_AuthenticationFailure;
use Horde_Notification_Handler;
use Horde_Registry;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;
use Psr\Log\LoggerInterface;
use Throwable;

class AjaxDispatchController implements RequestHandlerInterface
{
    /**
     * @param AjaxApplicationFactory   $ajaxFactory    Creates legacy Ajax Application instances
     * @param HordeCoreEnvelopeBuilder $envelope        Builds HordeCore JSON responses
     * @param Horde_Registry           $registry        For appInit() bootstrapping
     * @param Horde_Notification_Handler $notification  To drain notification stack
     * @param LoggerInterface          $logger          For logging stray output
     */
    public function __construct(
        private readonly AjaxApplicationFactory $ajaxFactory,
        private readonly HordeCoreEnvelopeBuilder $envelope,
        private readonly Horde_Registry $registry,
        private readonly Horde_Notification_Handler $notification,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Handle an AJAX dispatch request.
     *
     * Route params expected:
     * - 'app': application name (e.g. 'imp', 'kronolith')
     * - 'action': action identifier (e.g. 'listMessages', 'noop')
     *
     * @param ServerRequestInterface $request
     *
     * @return ResponseInterface
     */
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $route = $request->getAttribute('route', []);
        $app = $route['app'] ?? '';
        $action = $route['action'] ?? '';

        if ($app === '' || $action === '') {
            return $this->envelope->build(data: false);
        }

        $jsonhtml = $this->extractJsonhtml($request);

        // Bootstrap the app for legacy handler support.
        // When the registry already exists, appInit just pushes the app
        // onto the stack and returns the Application instance.
        try {
            Horde_Registry::appInit($app, [
                'authentication' => 'fallback',
            ]);
        } catch (Throwable) {
            return $this->envelope->build(data: false, jsonhtml: $jsonhtml);
        }

        // Build Variables from the PSR-7 request for the legacy Application
        $vars = $this->buildVariables($request);
        $token = $vars->get('token') ?? '';

        // Create the Ajax Application. Token check happens in the constructor.
        try {
            $ajax = $this->ajaxFactory->create($app, $vars, $action, $token);
        } catch (Horde_Exception) {
            // Token error → session timeout
            return $this->envelope->buildSessionTimeout(
                $this->buildLogoutUrl($app),
                $jsonhtml,
            );
        }

        // Dispatch the action
        ob_start();
        try {
            $ajax->doAction();

            $strayOutput = ob_get_clean();
            if ($strayOutput !== '' && $strayOutput !== false) {
                $this->logger->debug('Unexpected output during AJAX dispatch: ' . $strayOutput);
            }

            return $this->buildActionResponse($ajax, $jsonhtml);
        } catch (Horde_Exception_AuthenticationFailure $e) {
            ob_end_clean();
            return $this->envelope->buildNoAuth(
                $this->buildLogoutUrl($app, $e->getCode()),
                $jsonhtml,
            );
        } catch (Throwable $e) {
            ob_end_clean();
            $this->notification->push($e->getMessage(), 'horde.error');
            return $this->buildErrorResponse($jsonhtml);
        }
    }

    /**
     * Build a Variables instance from the PSR-7 request.
     *
     * Merges query parameters and parsed body into a single Variables
     * object that legacy handlers access via $this->vars.
     *
     * @param ServerRequestInterface $request
     *
     * @return Variables
     */
    private function buildVariables(ServerRequestInterface $request): Variables
    {
        $query = $request->getQueryParams();
        $body = $request->getParsedBody();
        $merged = is_array($body) ? array_merge($query, $body) : $query;

        return new Variables($merged);
    }

    /**
     * Check whether the jsonhtml option is set.
     *
     * @param ServerRequestInterface $request
     *
     * @return bool
     */
    private function extractJsonhtml(ServerRequestInterface $request): bool
    {
        $query = $request->getQueryParams();
        $body = $request->getParsedBody();

        if (is_array($body) && !empty($body['jsonhtml'])) {
            return true;
        }

        return !empty($query['jsonhtml']);
    }

    /**
     * Build the success response from a completed Ajax Application.
     *
     * Handles both plain data returns and Response object returns
     * from legacy handlers.
     *
     * @param Horde_Core_Ajax_Application $ajax
     * @param bool                        $jsonhtml
     *
     * @return ResponseInterface
     */
    private function buildActionResponse(
        Horde_Core_Ajax_Application $ajax,
        bool $jsonhtml,
    ): ResponseInterface {
        $data = $ajax->data;
        $tasks = $ajax->tasks;

        // Legacy handlers may return a Response object directly
        if ($data instanceof Horde_Core_Ajax_Response
            && !($data instanceof Horde_Core_Ajax_Response_HordeCore)) {
            // Non-HordeCore response — the handler wants raw control.
            // Fall back to the envelope builder with the response's data.
            $data = $data->data;
        } elseif ($data instanceof Horde_Core_Ajax_Response_HordeCore) {
            // HordeCore response — merge its tasks with the application's
            if ($data->tasks !== null) {
                if ($tasks === null) {
                    $tasks = $data->tasks;
                } else {
                    foreach ((array) $data->tasks as $key => $value) {
                        $tasks->$key = $value;
                    }
                }
            }
            $data = $data->data;
        }

        $msgs = $this->drainNotifications();

        return $this->envelope->build(
            data: $data,
            tasks: $tasks,
            msgs: $msgs,
            jsonhtml: $jsonhtml,
        );
    }

    /**
     * Build an error response with the current notification stack.
     *
     * @param bool $jsonhtml
     *
     * @return ResponseInterface
     */
    private function buildErrorResponse(bool $jsonhtml): ResponseInterface
    {
        $msgs = $this->drainNotifications();

        return $this->envelope->build(
            msgs: $msgs,
            jsonhtml: $jsonhtml,
        );
    }

    /**
     * Drain the notification stack into an array of message arrays.
     *
     * @return array<array{type: string, message: string, flags: array}>
     */
    private function drainNotifications(): array
    {
        $stack = $this->notification->notify([
            'listeners' => ['status', 'audio', 'webnotification'],
            'raw' => true,
        ]);

        if (empty($stack)) {
            return [];
        }

        $msgs = [];
        foreach ($stack as $event) {
            $msgs[] = array_filter([
                'flags' => $event->flags,
                'message' => $event->message,
                'type' => $event->type,
                'webnotify' => $event->webnotify ?? null,
            ]);
        }

        return $msgs;
    }

    /**
     * Build a logout URL for session timeout or auth failure responses.
     *
     * @param string   $app    Application name
     * @param int|null $reason Auth failure reason code
     *
     * @return string
     */
    private function buildLogoutUrl(string $app, ?int $reason = null): string
    {
        try {
            $params = ['reason' => $reason ?? \Horde_Auth::REASON_SESSION];
            $logoutUrl = $this->registry->getLogoutUrl($params);

            return (string) $logoutUrl;
        } catch (Throwable) {
            return '';
        }
    }
}
