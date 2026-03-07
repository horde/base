<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Auth;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Auth\ResponsiveLogoutController;
use Psr\Http\Message\ServerRequestInterface;
use Horde_Registry;

/**
 * Unit Test: ResponsiveLogoutController
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(ResponsiveLogoutController::class)]
class ResponsiveLogoutControllerTest extends TestCase
{
    private ServerRequestInterface $request;
    private Horde_Registry $registry;
    private mixed $originalGlobals;

    protected function setUp(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->registry = $this->createMock(Horde_Registry::class);

        // Save original globals
        $this->originalGlobals = $GLOBALS['injector'] ?? null;

        $this->request->method('getAttribute')
            ->with('registry')
            ->willReturn($this->registry);

        $this->registry->method('get')
            ->with('webroot', 'horde')
            ->willReturn('/horde');
    }

    protected function tearDown(): void
    {
        // Restore globals
        if ($this->originalGlobals !== null) {
            $GLOBALS['injector'] = $this->originalGlobals;
        } else {
            unset($GLOBALS['injector']);
        }
    }

    public function testHandleClearsAuthWhenUserAuthenticated(): void
    {
        $this->registry->method('getAuth')
            ->willReturn('testuser');

        $this->registry->expects($this->once())
            ->method('clearAuth');

        $this->request->method('getQueryParams')
            ->willReturn([]);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleDoesNotClearAuthWhenNotAuthenticated(): void
    {
        $this->registry->method('getAuth')
            ->willReturn(null);

        $this->registry->expects($this->never())
            ->method('clearAuth');

        $this->request->method('getQueryParams')
            ->willReturn([]);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertEquals(302, $response->getStatusCode());
    }

    public function testHandleRedirectsToLoginByDefault(): void
    {
        $this->registry->method('getAuth')
            ->willReturn('testuser');

        $this->request->method('getQueryParams')
            ->willReturn([]);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals(
            '/horde/auth/login?error=logout',
            $response->getHeaderLine('Location')
        );
    }

    public function testHandleUsesCustomRedirectUrl(): void
    {
        $this->registry->method('getAuth')
            ->willReturn('testuser');

        $this->request->method('getQueryParams')
            ->willReturn(['redirect' => '/custom/page']);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/custom/page', $response->getHeaderLine('Location'));
    }

    public function testHandleUsesCustomRedirectWithQueryParams(): void
    {
        $this->registry->method('getAuth')
            ->willReturn('testuser');

        $this->request->method('getQueryParams')
            ->willReturn(['redirect' => '/app?foo=bar&baz=qux']);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertEquals('/app?foo=bar&baz=qux', $response->getHeaderLine('Location'));
    }

    public function testHandleWorksWithDifferentWebroot(): void
    {
        $this->registry = $this->createMock(Horde_Registry::class);
        $this->registry->method('get')
            ->with('webroot', 'horde')
            ->willReturn('/custom-horde');
        $this->registry->method('getAuth')
            ->willReturn('testuser');

        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->request->method('getAttribute')
            ->with('registry')
            ->willReturn($this->registry);
        $this->request->method('getQueryParams')
            ->willReturn([]);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertEquals(
            '/custom-horde/auth/login?error=logout',
            $response->getHeaderLine('Location')
        );
    }

    public function testHandleWithNullRegistry(): void
    {
        $this->request = $this->createMock(ServerRequestInterface::class);
        $this->request->method('getAttribute')
            ->with('registry')
            ->willReturn(null);
        $this->request->method('getQueryParams')
            ->willReturn([]);

        $controller = new ResponsiveLogoutController();

        // Should throw Error when trying to call methods on null registry
        $this->expectException(\Error::class);
        $controller->handle($this->request);
    }

    public function testHandleReturnsRedirectResponse(): void
    {
        $this->registry->method('getAuth')
            ->willReturn(null);
        $this->request->method('getQueryParams')
            ->willReturn([]);

        $controller = new ResponsiveLogoutController();
        $response = $controller->handle($this->request);

        $this->assertInstanceOf(\Psr\Http\Message\ResponseInterface::class, $response);
        $this->assertEquals(302, $response->getStatusCode());
        $this->assertTrue($response->hasHeader('Location'));
    }
}
