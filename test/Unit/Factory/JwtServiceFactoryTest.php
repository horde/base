<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Factory;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Factory\JwtServiceFactory;
use Horde\Horde\Service\JwtService;
use Horde\Injector\Injector;
use InvalidArgumentException;

/**
 * Unit Test: JwtServiceFactory
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(JwtServiceFactory::class)]
class JwtServiceFactoryTest extends TestCase
{
    private Injector $injector;
    private array $originalConf;
    private array $originalServer;

    protected function setUp(): void
    {
        // Save original globals
        $this->originalConf = $GLOBALS['conf'] ?? [];
        $this->originalServer = $_SERVER;

        // Create mock injector
        $this->injector = $this->createMock(Injector::class);
    }

    protected function tearDown(): void
    {
        // Restore original globals
        $GLOBALS['conf'] = $this->originalConf;
        $_SERVER = $this->originalServer;
    }

    public function testCreateReturnsNullWhenJwtNotEnabled(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => false,
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertNull($result);
    }

    public function testCreateReturnsNullWhenJwtConfigMissing(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertNull($result);
    }

    public function testCreateReturnsJwtServiceWhenConfigured(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('a', 32), // 32 bytes minimum
                    'issuer' => 'test.example.com',
                    'access_ttl' => 1800,
                    'refresh_ttl' => 86400,
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateUsesDefaultIssuerFromServerName(): void
    {
        $_SERVER['SERVER_NAME'] = 'horde.test.com';
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('b', 32),
                    // issuer not specified
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateUsesDefaultIssuerWhenServerNameMissing(): void
    {
        unset($_SERVER['SERVER_NAME']);
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('c', 32),
                    // issuer not specified
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateUsesDefaultTtlValues(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('d', 32),
                    'issuer' => 'test.com',
                    // TTL values not specified
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateThrowsWhenSecretMissing(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    // secret not specified
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT is enabled but no secret is configured');

        $factory = new JwtServiceFactory();
        $factory->create($this->injector);
    }

    public function testCreateThrowsWhenSecretEmpty(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => '',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT is enabled but no secret is configured');

        $factory = new JwtServiceFactory();
        $factory->create($this->injector);
    }

    public function testCreateThrowsWhenSecretTooShort(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => 'short', // Only 5 bytes, needs 32
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must be at least 256 bits (32 bytes)');

        $factory = new JwtServiceFactory();
        $factory->create($this->injector);
    }

    public function testCreateThrowsWhenSecret31Bytes(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('x', 31), // 31 bytes, needs 32
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret must be at least 256 bits (32 bytes)');

        $factory = new JwtServiceFactory();
        $factory->create($this->injector);
    }

    public function testCreateSucceedsWithExactly32ByteSecret(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('x', 32), // Exactly 32 bytes
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }

    public function testCreateSucceedsWithLongerSecret(): void
    {
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('x', 64), // 64 bytes, well above minimum
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }
}
