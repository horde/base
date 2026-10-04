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

use Horde_Exception;
use Horde_Registry;
use Horde\Exception\HordeThrowable;
use Horde\Injector\Injector;
use Psr\Log\LoggerInterface;
use Throwable;

/**
 * Collect login params (and optionally posted values) from every
 * registered application that declares the 'loginparams' auth capability,
 * independently of which app/driver is currently authenticating Horde
 * itself.
 *
 * This lets an app's login UI (e.g. a mail-server selector) appear and
 * be honored even when Horde's own auth driver is unrelated to that app
 * (LDAP, SQL etc).
 *
 */
class AppLoginParamCollector
{
    public function __construct(
        // TODO: Find a way to capability-check without using the legacy registry.
        private readonly Horde_Registry $registry,
        // TODO: Wean of injecting the DIC
        private readonly Injector $injector,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * Iterate all registered apps and collect login params from those that
     * advertise the 'loginparams' auth capability.
     *
     * @param bool                  $collectPost   Also extract posted values
     *                                             for each collected field.
     * @param array<string, mixed>  $postedFields  Source of posted values when
     *                                             $collectPost is true.
     *                                             Typically $_POST or the
     *                                             backendParams from a
     *                                             LoginAttempt VO.
     *
     * @return array{
     *     params:   array<string, array>,
     *     js_code:  array,
     *     js_files: array,
     *     posted:   array<string, array<string, mixed>>
     * } 'posted' is keyed by app name, then by field name.
     */
    public function collect(
        bool $collectPost = false,
        array $postedFields = [],
    ): array {
        $params = $jsCode = $jsFiles = [];
        $posted = [];

        // perms=null bypasses the permission check.  We are pre-auth here,
        // there is no user to check permissions against.
        foreach ($this->registry->listApps(null, false, null) as $app) {
            if ($app === 'horde') {
                continue;
            }

            try {
                $appAuth = $this->injector
                    ->getInstance('Horde_Core_Factory_Auth')
                    ->create($app);

                if (!$appAuth->hasCapability('loginparams')) {
                    continue;
                }

                $result = $appAuth->getLoginParams();
                $params  = array_merge($params, $result['params'] ?? []);
                $jsCode  = array_merge($jsCode, $result['js_code'] ?? []);
                $jsFiles = array_merge($jsFiles, $result['js_files'] ?? []);

                if ($collectPost) {
                    foreach (array_keys($result['params'] ?? []) as $key) {
                        if (array_key_exists($key, $postedFields)) {
                            $posted[$app][$key] = $postedFields[$key];
                        }
                    }
                }
            } catch (Horde_Exception|HordeThrowable $e) {
                // Expected: this app declined to provide login params (not
                // configured, not applicable, etc.).  Skip silently.
                continue;
            } catch (Throwable $e) {
                // Unexpected failure (misconfigured DI, broken app code etc).
                // Do not let one broken app take down the login page for
                // everyone, but do log it so the operator sees the problem.
                $this->logger->error(
                    'AppLoginParamCollector: collecting login params from app '
                    . $app . ' failed: ' . $e->getMessage(),
                    ['exception' => $e],
                );
                continue;
            }
        }

        return [
            'params'   => $params,
            'js_code'  => $jsCode,
            'js_files' => $jsFiles,
            'posted'   => $posted,
        ];
    }
}
