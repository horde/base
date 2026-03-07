<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Traits;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Traits\JsonResponseTrait;
use JsonException;

/**
 * Unit Test: JsonResponseTrait
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(JsonResponseTrait::class)]
class JsonResponseTraitTest extends TestCase
{
    private object $traitUser;

    protected function setUp(): void
    {
        // Create anonymous class that uses the trait
        $this->traitUser = new class {
            use JsonResponseTrait;

            // Expose protected methods for testing
            public function testJsonResponse(array $data, int $status = 200, int $jsonFlags = JSON_THROW_ON_ERROR)
            {
                return $this->jsonResponse($data, $status, $jsonFlags);
            }

            public function testJsonError(string $message, int $status = 400, string $errorCode = 'error')
            {
                return $this->jsonError($message, $status, $errorCode);
            }

            public function testJsonUnauthorized(string $message = 'Unauthorized')
            {
                return $this->jsonUnauthorized($message);
            }
        };
    }

    public function testJsonResponse(): void
    {
        $data = ['foo' => 'bar', 'baz' => 123];
        $response = $this->traitUser->testJsonResponse($data);

        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals($data, $decoded);
    }

    public function testJsonResponseWithCustomStatus(): void
    {
        $data = ['status' => 'created'];
        $response = $this->traitUser->testJsonResponse($data, 201);

        $this->assertEquals(201, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));
    }

    public function testJsonResponseWithCustomFlags(): void
    {
        $data = ['url' => 'https://example.com/test'];
        $response = $this->traitUser->testJsonResponse($data, 200, JSON_UNESCAPED_SLASHES);

        $body = (string) $response->getBody();
        $this->assertStringContainsString('https://example.com/test', $body);
        $this->assertStringNotContainsString('https:\/\/', $body);
    }

    public function testJsonError(): void
    {
        $response = $this->traitUser->testJsonError('Something went wrong', 400, 'bad_request');

        $this->assertEquals(400, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals('bad_request', $decoded['error']);
        $this->assertEquals('Something went wrong', $decoded['message']);
    }

    public function testJsonErrorWithDefaults(): void
    {
        $response = $this->traitUser->testJsonError('Bad request');

        $this->assertEquals(400, $response->getStatusCode());

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals('error', $decoded['error']);
        $this->assertEquals('Bad request', $decoded['message']);
    }

    public function testJsonUnauthorized(): void
    {
        $response = $this->traitUser->testJsonUnauthorized();

        $this->assertEquals(401, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('Content-Type'));

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals('unauthorized', $decoded['error']);
        $this->assertEquals('Unauthorized', $decoded['message']);
    }

    public function testJsonUnauthorizedWithCustomMessage(): void
    {
        $response = $this->traitUser->testJsonUnauthorized('Invalid credentials');

        $this->assertEquals(401, $response->getStatusCode());

        $body = (string) $response->getBody();
        $decoded = json_decode($body, true);

        $this->assertEquals('unauthorized', $decoded['error']);
        $this->assertEquals('Invalid credentials', $decoded['message']);
    }

    public function testJsonResponseThrowsOnInvalidJson(): void
    {
        $this->expectException(JsonException::class);

        // Create data that cannot be JSON encoded (like a resource)
        $data = ['resource' => fopen('php://memory', 'r')];
        $this->traitUser->testJsonResponse($data);
    }
}
