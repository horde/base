<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Traits;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Traits\RedirectResponseTrait;

/**
 * Unit Test: RedirectResponseTrait
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(RedirectResponseTrait::class)]
class RedirectResponseTraitTest extends TestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        // Create anonymous class that uses the trait
        $this->traitUser = new class {
            use RedirectResponseTrait;

            // Expose protected methods for testing
            public function testRedirect(string $url, int $status = 302)
            {
                return $this->redirect($url, $status);
            }

            public function testRedirectToLogin(string $loginUrl, ?string $returnUrl = null)
            {
                return $this->redirectToLogin($loginUrl, $returnUrl);
            }
        };
    }

    public function testRedirect(): void
    {
        $response = $this->traitUser->testRedirect('/target-page');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/target-page', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithTemporaryStatus(): void
    {
        $response = $this->traitUser->testRedirect('/temporary', 302);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/temporary', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithPermanentStatus(): void
    {
        $response = $this->traitUser->testRedirect('/permanent', 301);

        $this->assertEquals(301, $response->getStatusCode());
        $this->assertEquals('/permanent', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithSeeOtherStatus(): void
    {
        $response = $this->traitUser->testRedirect('/see-other', 303);

        $this->assertEquals(303, $response->getStatusCode());
        $this->assertEquals('/see-other', $response->getHeaderLine('Location'));
    }

    public function testRedirectWithAbsoluteUrl(): void
    {
        $response = $this->traitUser->testRedirect('https://example.com/page');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('https://example.com/page', $response->getHeaderLine('Location'));
    }

    public function testRedirectToLogin(): void
    {
        $response = $this->traitUser->testRedirectToLogin('/auth/login');

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/auth/login', $response->getHeaderLine('Location'));
    }

    public function testRedirectToLoginWithReturnUrl(): void
    {
        $response = $this->traitUser->testRedirectToLogin('/auth/login', '/dashboard');

        $this->assertEquals(302, $response->getStatusCode());

        $location = $response->getHeaderLine('Location');
        $this->assertStringStartsWith('/auth/login?url=', $location);
        $this->assertStringContainsString(urlencode('/dashboard'), $location);
    }

    public function testRedirectToLoginWithComplexReturnUrl(): void
    {
        $returnUrl = '/app?foo=bar&baz=qux';
        $response = $this->traitUser->testRedirectToLogin('/login', $returnUrl);

        $location = $response->getHeaderLine('Location');
        $this->assertStringStartsWith('/login?url=', $location);
        $this->assertStringContainsString(urlencode($returnUrl), $location);

        // Verify the return URL can be decoded properly
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertEquals($returnUrl, $query['url']);
    }

    public function testRedirectToLoginWithNullReturnUrl(): void
    {
        $response = $this->traitUser->testRedirectToLogin('/auth/login', null);

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertEquals('/auth/login', $response->getHeaderLine('Location'));
        $this->assertStringNotContainsString('?url=', $response->getHeaderLine('Location'));
    }

    public function testRedirectToLoginWithSpecialCharactersInReturnUrl(): void
    {
        $returnUrl = '/search?q=hello world&lang=en';
        $response = $this->traitUser->testRedirectToLogin('/login', $returnUrl);

        $location = $response->getHeaderLine('Location');

        // Verify proper URL encoding
        parse_str(parse_url($location, PHP_URL_QUERY), $query);
        $this->assertEquals($returnUrl, $query['url']);
    }
}
