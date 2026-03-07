<?php

declare(strict_types=1);

namespace Horde\Horde\Test\Unit\Factory;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\CoversClass;
use Horde\Horde\Factory\AuthenticationServiceFactory;
use Horde\Horde\Factory\JwtServiceFactory;
use Horde\Horde\Service\AuthenticationService;
use Horde\Horde\Service\JwtService;
use Horde\Injector\Injector;
use Horde_Registry;

/**
 * Unit Test: AuthenticationServiceFactory
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
#[CoversClass(AuthenticationServiceFactory::class)]
class AuthenticationServiceFactoryTest extends TestCase
{
    private Injector $injector;
    private Horde_Registry $registry;
    private array $originalConf;

    protected function setUp(): void
    {
        // Save original globals
        $this->originalConf = $GLOBALS['conf'] ?? [];

        // Create mock registry
        $this->registry = $this->createMock(Horde_Registry::class);

        // Create mock injector
        $this->injector = $this->createMock(Injector::class);
        $this->injector->method('getInstance')
            ->with('Horde_Registry')
            ->willReturn($this->registry);
    }

    protected function tearDown(): void
    {
        // Restore original globals
        $GLOBALS['conf'] = $this->originalConf;
    }

    public function testCreateReturnsAuthenticationService(): void
    {
        // Configure JWT as disabled
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => false,
                ],
            ],
        ];

        $factory = new AuthenticationServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(AuthenticationService::class, $result);
    }

    public function testCreateWithJwtEnabled(): void
    {
        // Configure valid JWT
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    'secret' => str_repeat('a', 32),
                    'issuer' => 'test.com',
                ],
            ],
        ];

        $factory = new AuthenticationServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(AuthenticationService::class, $result);
    }

    public function testCreateWithJwtMisconfigured(): void
    {
        // Configure JWT as enabled but with missing secret
        // Should gracefully handle and create service without JWT
        $GLOBALS['conf'] = [
            'auth' => [
                'jwt' => [
                    'enabled' => true,
                    // secret missing - will cause exception
                ],
            ],
        ];

        $factory = new AuthenticationServiceFactory();
        $result = $factory->create($this->injector);

        // Should still create AuthenticationService, just without JWT
        $this->assertInstanceOf(AuthenticationService::class, $result);
    }

    public function testCreateWithNoJwtConfig(): void
    {
        // No JWT configuration at all
        $GLOBALS['conf'] = [
            'auth' => [],
        ];

        $factory = new AuthenticationServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(AuthenticationService::class, $result);
    }

    public function testCreateWithEmptyConf(): void
    {
        // Empty configuration
        $GLOBALS['conf'] = [];

        $factory = new AuthenticationServiceFactory();
        $result = $factory->create($this->injector);

        $this->assertInstanceOf(AuthenticationService::class, $result);
    }

    public function testCreateAlwaysSucceedsRegardlessOfJwtConfig(): void
    {
        // Test various JWT configurations - service should always be created
        $configs = [
            // No JWT config
            [],
            // JWT disabled
            ['auth' => ['jwt' => ['enabled' => false]]],
            // JWT enabled but invalid secret
            ['auth' => ['jwt' => ['enabled' => true, 'secret' => 'short']]],
            // JWT enabled but no secret
            ['auth' => ['jwt' => ['enabled' => true]]],
            // JWT valid
            ['auth' => ['jwt' => ['enabled' => true, 'secret' => str_repeat('x', 32)]]],
        ];

        $factory = new AuthenticationServiceFactory();

        foreach ($configs as $config) {
            $GLOBALS['conf'] = $config;
            $result = $factory->create($this->injector);

            $this->assertInstanceOf(
                AuthenticationService::class,
                $result,
                'Service should be created regardless of JWT config'
            );
        }
    }
}
