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
    private string $testSecretFile;

    protected function setUp(): void
    {
        // Save original globals
        $this->originalConf = $GLOBALS['conf'] ?? [];
        $this->originalServer = $_SERVER;

        // Create mock injector
        $this->injector = $this->createMock(Injector::class);

        // Create a temporary secret file for tests
        $this->testSecretFile = sys_get_temp_dir() . '/jwt_test_secret_' . uniqid();
    }

    protected function tearDown(): void
    {
        // Restore original globals
        $GLOBALS['conf'] = $this->originalConf;
        $_SERVER = $this->originalServer;

        // Clean up test secret file
        if (file_exists($this->testSecretFile)) {
            unlink($this->testSecretFile);
        }
    }

    private function createSecretFile(string $content): void
    {
        file_put_contents($this->testSecretFile, $content);
        chmod($this->testSecretFile, 0o600);
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
        $this->createSecretFile(str_repeat('a', 32));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
        $this->createSecretFile(str_repeat('b', 32));

        $_SERVER['SERVER_NAME'] = 'horde.test.com';
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
        $this->createSecretFile(str_repeat('c', 32));

        unset($_SERVER['SERVER_NAME']);
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
        $this->createSecretFile(str_repeat('d', 32));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
                    'secret_file' => '/nonexistent/path/to/secret',
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT is enabled but secret file does not exist');

        $factory = new JwtServiceFactory();
        $factory->create($this->injector);
    }

    public function testCreateThrowsWhenSecretEmpty(): void
    {
        $this->createSecretFile('');

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                ],
            ],
        ];

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('JWT secret file is empty');

        $factory = new JwtServiceFactory();
        $factory->create($this->injector);
    }

    public function testCreateThrowsWhenSecretTooShort(): void
    {
        $this->createSecretFile('short');

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
        $this->createSecretFile(str_repeat('x', 31));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
        $this->createSecretFile(str_repeat('x', 32));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
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
        $this->createSecretFile(str_repeat('x', 64));

        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret_file' => $this->testSecretFile,
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new JwtServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(JwtService::class, $result);
    }
}
