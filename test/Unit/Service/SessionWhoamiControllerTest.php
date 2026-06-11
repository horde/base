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

namespace Horde\Horde\Test\Unit\Service;

use Horde\Core\Auth\HasCredentialsState;
use Horde\Core\Config\ConfigLoader;
use Horde\Core\Config\State;
use Horde\Core\Middleware\HordeSessionMiddleware;
use Horde\Core\Session\HordeSession;
use Horde\Horde\Service\SessionWhoamiController;
use Horde\SessionHandler\SessionId;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Unit tests for {@see SessionWhoamiController}.
 *
 * Construction strategy: real `HordeSession` and ConfigLoader stub.
 * The controller builds its own `AuthCredentialStore` from the
 * request's session, so tests do not have to wire one. Real
 * `HordeSession` is cheaper than mocking it: it's a value object
 * whose constructor takes only a `SessionId` and a data array.
 */
#[CoversClass(SessionWhoamiController::class)]
final class SessionWhoamiControllerTest extends TestCase
{
    /** Build a real `HordeSession` carrying the supplied scoped data. */
    private function makeSession(array $hordeScope = [], int $beginOffset = 0): HordeSession
    {
        $data = [];
        if ($beginOffset !== 0) {
            $data['_b'] = time() - $beginOffset;
        }
        $session = new HordeSession(new SessionId('test-session-id'), $data);
        foreach ($hordeScope as $key => $value) {
            $session->setScoped('horde', $key, $value);
        }
        return $session;
    }

    private function makeRequest(?HordeSession $session): ServerRequestInterface
    {
        $request = $this->createStub(ServerRequestInterface::class);
        $request->method('getAttribute')
            ->willReturnCallback(static function (string $name) use ($session): mixed {
                return $name === HordeSessionMiddleware::ATTRIBUTE_SESSION ? $session : null;
            });
        return $request;
    }

    /** Build a `ConfigLoader` whose `load('horde')` returns a `State` over the given conf array. */
    private function makeConfigLoader(array $conf): ConfigLoader
    {
        $loader = $this->createStub(ConfigLoader::class);
        $loader->method('load')->willReturn(new State($conf));
        return $loader;
    }

    public function testReturnsJsonForAuthenticatedUser(): void
    {
        $session = $this->makeSession(['auth/userId' => 'alice']);
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest($session));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($body['authenticated']);
        self::assertSame('alice', $body['user_id']);
        self::assertSame('test-session-id', $body['session_id']);
    }

    public function testRejectsAnonymousByDefault(): void
    {
        $session = $this->makeSession();
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest($session));

        self::assertSame(401, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('unauthorized', $body['error']);
    }

    public function testServesAnonymousInPublicMode(): void
    {
        $session = $this->makeSession();
        $controller = new SessionWhoamiController(
            $this->makeConfigLoader(['session_whoami' => ['public' => true]]),
        );

        $response = $controller->handle($this->makeRequest($session));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertFalse($body['authenticated']);
        self::assertNull($body['user_id']);
        self::assertSame([], $body['credentials']);
    }

    public function testReturnsJsonForAuthenticatedUserPublicMode(): void
    {
        $session = $this->makeSession(['auth/userId' => 'alice']);
        $controller = new SessionWhoamiController(
            $this->makeConfigLoader(['session_whoami' => ['public' => true]]),
        );

        $response = $controller->handle($this->makeRequest($session));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertTrue($body['authenticated']);
        self::assertSame('alice', $body['user_id']);
    }

    public function testReturnsServerErrorIfSessionAttributeMissing(): void
    {
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest(null));

        self::assertSame(500, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame('session_unavailable', $body['error']);
    }

    public function testIncludesCredentialStateMap(): void
    {
        $session = $this->makeSession(['auth/userId' => 'alice']);
        // Seed the state slots directly. The controller's prefix walk
        // reads the `auth_app_state/` slots and asks the per-request
        // AuthCredentialStore (built inside handle()) to interpret them.
        $session->setScoped('horde', 'auth_app_state/imp', HasCredentialsState::Present->value);
        $session->setScoped('horde', 'auth_app_state/kronolith', HasCredentialsState::Invalidated->value);
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest($session));

        self::assertSame(200, $response->getStatusCode());
        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(HasCredentialsState::Present->value, $body['credentials']['imp'] ?? null);
        self::assertSame(HasCredentialsState::Invalidated->value, $body['credentials']['kronolith'] ?? null);
    }

    public function testSessionAgeReportsTimeSinceBegin(): void
    {
        $session = $this->makeSession(['auth/userId' => 'alice'], beginOffset: 100);
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest($session));

        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertGreaterThanOrEqual(99, $body['session_age_seconds']);
        self::assertLessThanOrEqual(101, $body['session_age_seconds']);
    }

    public function testSessionAgeIsZeroWhenBeginUnknown(): void
    {
        $session = $this->makeSession(['auth/userId' => 'alice']);
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest($session));

        $body = json_decode((string) $response->getBody(), true, flags: JSON_THROW_ON_ERROR);
        self::assertSame(0, $body['session_age_seconds']);
    }

    public function testEmptyUserIdTreatedAsAnonymous(): void
    {
        $session = $this->makeSession(['auth/userId' => '']);
        $controller = new SessionWhoamiController($this->makeConfigLoader([]));

        $response = $controller->handle($this->makeRequest($session));

        self::assertSame(401, $response->getStatusCode());
    }
}
