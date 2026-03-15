<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Admin\Traits;

use Horde\Horde\Admin\Traits\AdminAuthenticationTrait;
use Horde\Core\Config\State;
use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Psr\Http\Message\ServerRequestInterface;

/**
 * Tests for AdminAuthenticationTrait
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(AdminAuthenticationTrait::class)]
class AdminAuthenticationTraitTest extends TestCase
{
    use AdminAuthenticationTrait;

    protected function setUp(): void
    {
        $this->config = $this->createMock(State::class);
    }

    public function testAuthenticateRejectsDisabledApi(): void
    {
        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, false],
            ['admin_api.admin_secret', '', 'validlongsecret' . str_repeat('x', 40)],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsEmptySecret(): void
    {
        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', ''],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsNullSecret(): void
    {
        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', null],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsUnsetSecret(): void
    {
        // Config returns default empty string when key doesn't exist
        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', ''],  // Simulates unset key returning default
        ]);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsShortSecret(): void
    {
        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', 'tooshort'],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsBarelyShortSecret(): void
    {
        // 50 characters - just below the 51 minimum
        $shortSecret = str_repeat('a', 50);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $shortSecret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateAcceptsMinimumLengthSecret(): void
    {
        // 51 characters - exactly at minimum
        $validSecret = str_repeat('a', 51);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $validSecret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer ' . $validSecret);

        $this->assertTrue($this->authenticate($request));
    }

    public function testAuthenticateAcceptsStandardSecret(): void
    {
        // 64 characters - standard length
        $validSecret = str_repeat('a', 64);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $validSecret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer ' . $validSecret);

        $this->assertTrue($this->authenticate($request));
    }

    public function testAuthenticateRejectsMissingAuthHeader(): void
    {
        $validSecret = str_repeat('a', 64);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $validSecret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('');

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsInvalidAuthFormat(): void
    {
        $validSecret = str_repeat('a', 64);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $validSecret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Basic ' . base64_encode('user:pass'));

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateRejectsWrongSecret(): void
    {
        $configuredSecret = str_repeat('a', 64);
        $providedSecret = str_repeat('b', 64);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $configuredSecret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer ' . $providedSecret);

        $this->assertFalse($this->authenticate($request));
    }

    public function testAuthenticateAcceptsCorrectSecret(): void
    {
        $secret = str_repeat('a', 64);

        $this->config->method('get')->willReturnMap([
            ['admin_api.enabled', false, true],
            ['admin_api.admin_secret', '', $secret],
        ]);

        $request = $this->createMock(ServerRequestInterface::class);
        $request->method('getHeaderLine')
            ->with('Authorization')
            ->willReturn('Bearer ' . $secret);

        $this->assertTrue($this->authenticate($request));
    }

    public function testGetMinSecretLength(): void
    {
        $this->assertEquals(51, $this->getMinSecretLength());
    }

    public function testGetStandardSecretLength(): void
    {
        $this->assertEquals(64, $this->getStandardSecretLength());
    }

    public function testGenerateSecret(): void
    {
        $secret = $this->generateSecret();

        $this->assertIsString($secret);
        $this->assertEquals(64, strlen($secret));
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $secret);
    }

    public function testGeneratedSecretIsRandom(): void
    {
        $secret1 = $this->generateSecret();
        $secret2 = $this->generateSecret();

        $this->assertNotEquals($secret1, $secret2);
    }
}
