<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Traits;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Traits\HtmlResponseTrait;

/**
 * Unit Test: HtmlResponseTrait
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(HtmlResponseTrait::class)]
class HtmlResponseTraitTest extends TestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        // Create anonymous class that uses the trait
        $this->traitUser = new class {
            use HtmlResponseTrait;

            // Expose protected methods for testing
            public function testEscapeHtml(string $text): string
            {
                return $this->escapeHtml($text);
            }

            public function testHtmlResponse(string $html, int $status = 200)
            {
                return $this->htmlResponse($html, $status);
            }
        };
    }

    public function testEscapeHtml(): void
    {
        $result = $this->traitUser->testEscapeHtml('<script>alert("xss")</script>');

        $this->assertEquals('&lt;script&gt;alert(&quot;xss&quot;)&lt;/script&gt;', $result);
        $this->assertStringNotContainsString('<script>', $result);
    }

    public function testEscapeHtmlWithDoubleQuotes(): void
    {
        $result = $this->traitUser->testEscapeHtml('Hello "World"');

        $this->assertEquals('Hello &quot;World&quot;', $result);
    }

    public function testEscapeHtmlWithSingleQuotes(): void
    {
        $result = $this->traitUser->testEscapeHtml("Hello 'World'");

        // HTML5 uses &apos; for single quotes
        $this->assertEquals('Hello &apos;World&apos;', $result);
    }

    public function testEscapeHtmlWithAmpersand(): void
    {
        $result = $this->traitUser->testEscapeHtml('Tom & Jerry');

        $this->assertEquals('Tom &amp; Jerry', $result);
    }

    public function testEscapeHtmlWithMultipleSpecialChars(): void
    {
        $input = '<a href="test.php?foo=bar&baz=qux">Link\'s text</a>';
        $result = $this->traitUser->testEscapeHtml($input);

        $this->assertEquals(
            '&lt;a href=&quot;test.php?foo=bar&amp;baz=qux&quot;&gt;Link&apos;s text&lt;/a&gt;',
            $result
        );
    }

    public function testEscapeHtmlWithUtf8Characters(): void
    {
        $result = $this->traitUser->testEscapeHtml('Héllo Wörld 世界');

        // UTF-8 characters should be preserved, not escaped
        $this->assertEquals('Héllo Wörld 世界', $result);
    }

    public function testEscapeHtmlWithEmptyString(): void
    {
        $result = $this->traitUser->testEscapeHtml('');

        $this->assertEquals('', $result);
    }

    public function testHtmlResponse(): void
    {
        $html = '<html><body><h1>Test</h1></body></html>';
        $response = $this->traitUser->testHtmlResponse($html);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $this->assertEquals($html, $body);
    }

    public function testHtmlResponseWithCustomStatus(): void
    {
        $html = '<html><body><h1>Not Found</h1></body></html>';
        $response = $this->traitUser->testHtmlResponse($html, 404);

        $this->assertEquals(404, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
    }

    public function testHtmlResponseWith500Status(): void
    {
        $html = '<html><body><h1>Internal Server Error</h1></body></html>';
        $response = $this->traitUser->testHtmlResponse($html, 500);

        $this->assertEquals(500, $response->getStatusCode());
    }

    public function testHtmlResponseWithEmptyContent(): void
    {
        $response = $this->traitUser->testHtmlResponse('');

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('text/html; charset=utf-8', $response->getHeaderLine('Content-Type'));
        $this->assertEquals('', (string) $response->getBody());
    }

    public function testHtmlResponseWithLargeContent(): void
    {
        $html = str_repeat('<p>Lorem ipsum dolor sit amet.</p>', 1000);
        $response = $this->traitUser->testHtmlResponse($html);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals($html, (string) $response->getBody());
    }

    public function testHtmlResponsePreservesUtf8(): void
    {
        $html = '<html><body><p>Héllo Wörld 世界</p></body></html>';
        $response = $this->traitUser->testHtmlResponse($html);

        $body = (string) $response->getBody();
        $this->assertEquals($html, $body);
        $this->assertStringContainsString('Héllo Wörld 世界', $body);
    }

    public function testHtmlResponseContentTypeIsCorrect(): void
    {
        $response = $this->traitUser->testHtmlResponse('<html></html>');

        // Verify charset is explicitly UTF-8
        $contentType = $response->getHeaderLine('Content-Type');
        $this->assertStringContainsString('charset=utf-8', $contentType);
    }
}
