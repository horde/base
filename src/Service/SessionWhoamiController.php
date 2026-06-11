<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Horde
 */

namespace Horde\Horde\Service;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Middleware\HordeSessionMiddleware;
use Horde\Core\Session\HordeSession;
use Horde\Horde\Traits\JsonResponseTrait;
use Horde\Injector\Attribute\Factory;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Modern PSR-15 demo route: GET /api/v1/session/whoami.
 *
 * Returns JSON describing the current session: id, authenticated user
 * (if any), session age, per-app credential states. The endpoint
 * exists to prove the modern session middleware stack works
 * end-to-end without HordeCore, AuthHordeSession, or Horde_Registry.
 *
 * Auth gate is config-driven: when `$conf['session_whoami']['public']`
 * is unset or false, anonymous callers receive 401. When true,
 * anonymous callers see a populated body with their freshly minted
 * session id and an empty credentials map.
 *
 * The auth gate will move to the modern permissions stack once that
 * lands. Until then, the config switch is the smallest knob that
 * works without legacy plumbing.
 *
 * @see \Horde\Horde\Service\SessionWhoamiControllerFactory
 * @see \Horde\Core\Middleware\HordeSessionMiddleware
 * @see \Horde\Core\Middleware\JwtSessionLoader
 */
#[Factory(factory: SessionWhoamiControllerFactory::class, method: 'create')]
final class SessionWhoamiController implements RequestHandlerInterface
{
    use JsonResponseTrait;

    /** Slot prefix used by AuthCredentialStore for per-app state. */
    private const CREDENTIAL_STATE_PREFIX = 'auth_app_state/';

    public function __construct(
        private readonly ConfigLoader $configLoader,
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $session = $request->getAttribute(HordeSessionMiddleware::ATTRIBUTE_SESSION);
        if (!$session instanceof HordeSession) {
            // The middleware always sets this attribute. Missing means
            // the route is misconfigured. Fail loudly rather than serve
            // a silent 200 with empty data.
            return $this->jsonError(
                'Session middleware did not populate the session attribute.',
                500,
                'session_unavailable',
            );
        }

        $userId = $this->extractUserId($session);
        $isAuthenticated = $userId !== null;

        if (!$isAuthenticated && !$this->isPublic()) {
            return $this->jsonUnauthorized('Authentication required.');
        }

        // Build a fresh AuthCredentialStore bound to the request's
        // session. The DI-resolved store is keyed to whatever session
        // HordeSessionFactory produced at request start, NOT the one
        // the middleware loaded from the cookie. Constructing locally
        // is the prototype-correct path.
        $credentialStore = new AuthCredentialStore($session);

        return $this->jsonResponse([
            'session_id' => (string) $session->getId(),
            'authenticated' => $isAuthenticated,
            'user_id' => $userId,
            'session_age_seconds' => $this->sessionAgeSeconds($session),
            'credentials' => $this->collectCredentialStates($session, $credentialStore),
        ]);
    }

    /**
     * Whether anonymous callers may see whoami output.
     *
     * Defaults to false (gated). Operators flip to true for diagnostic
     * smoke testing of the modern session stack.
     */
    private function isPublic(): bool
    {
        $state = $this->configLoader->load('horde');
        $value = $state->get('session_whoami.public', false);
        return (bool) ($value ?? false);
    }

    /**
     * Read the authenticated user id from the standard session slot.
     *
     * Treats empty string and non-string values as anonymous.
     */
    private function extractUserId(HordeSession $session): ?string
    {
        $value = $session->getScoped('horde', 'auth/userId');
        return is_string($value) && $value !== '' ? $value : null;
    }

    /** Seconds since session begin, or 0 if begin is unknown. */
    private function sessionAgeSeconds(HordeSession $session): int
    {
        $begin = $session->getSessionBegin();
        if ($begin === null) {
            return 0;
        }
        return max(0, time() - $begin->getTimestamp());
    }

    /**
     * Build the per-app credential-state map.
     *
     * Walks every key under the `horde` scope that begins with the
     * `auth_app_state/` prefix and asks `AuthCredentialStore` for the
     * matching state. Reports only apps with explicit state recorded;
     * apps with implicit `NeverHad` are omitted.
     *
     * TODO: this prototype knowingly couples to the slot prefix that
     * is `AuthCredentialStore`'s private layout detail. When a real
     * consumer needs per-app state listing, factor a method out of
     * this controller into `AuthCredentialStore` and drop the prefix
     * walk. Tracked in
     * `horde-development/strategies/modern-session-migration/session-whoami-demo-plan-2026-06-11.md`.
     *
     * @return array<string, string>
     */
    private function collectCredentialStates(HordeSession $session, AuthCredentialStore $credentialStore): array
    {
        $states = [];
        foreach ($session->keysForApp('horde') as $key) {
            if (!str_starts_with($key, self::CREDENTIAL_STATE_PREFIX)) {
                continue;
            }
            $app = substr($key, strlen(self::CREDENTIAL_STATE_PREFIX));
            if ($app === '') {
                continue;
            }
            $states[$app] = $credentialStore->getState($app)->value;
        }
        return $states;
    }
}
