<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

namespace Horde\Horde\Unit\LoginTasks\Task;

use Horde_Config;
use Horde_Core_Factory_HttpClient;
use Horde_Exception;
use Horde_Http_Client;
use Horde_Injector;
use Horde_LoginTasks_Task_UpgradeCheck;
use Horde_Notification_Handler;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use stdClass;

/**
 * Tests for UpgradeCheck login task.
 *
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
#[CoversClass(Horde_LoginTasks_Task_UpgradeCheck::class)]
class UpgradeCheckTest extends TestCase
{
    private $originalGlobals = [];

    protected function setUp(): void
    {
        // Save original globals
        $this->originalGlobals = [
            'registry' => $GLOBALS['registry'] ?? null,
            'notification' => $GLOBALS['notification'] ?? null,
            'injector' => $GLOBALS['injector'] ?? null,
        ];
    }

    protected function tearDown(): void
    {
        // Restore original globals
        foreach ($this->originalGlobals as $key => $value) {
            if ($value === null) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }
    }

    /**
     * Create a mock injector with HTTP client factory that throws exception.
     */
    private function createMockInjectorThatThrows(): Horde_Injector
    {
        $httpClient = $this->createMock(Horde_Http_Client::class);
        $httpClient->method('get')
            ->willThrowException(new Horde_Exception('Network error'));

        $httpFactory = $this->createMock(Horde_Core_Factory_HttpClient::class);
        $httpFactory->method('create')
            ->willReturn($httpClient);

        $injector = $this->createMock(Horde_Injector::class);
        $injector->method('getInstance')
            ->with('Horde_Core_Factory_HttpClient')
            ->willReturn($httpFactory);

        return $injector;
    }

    /**
     * Create a mock injector with HTTP client factory that returns empty versions.
     */
    private function createMockInjectorWithEmptyVersions(): Horde_Injector
    {
        // Create a mock response object
        $response = new class {
            public $code = 200;
            public function getBody()
            {
                return json_encode([]);
            }
        };

        $httpClient = $this->createMock(Horde_Http_Client::class);
        $httpClient->method('get')
            ->willReturn($response);

        $httpFactory = $this->createMock(Horde_Core_Factory_HttpClient::class);
        $httpFactory->method('create')
            ->willReturn($httpClient);

        $injector = $this->createMock(Horde_Injector::class);
        $injector->method('getInstance')
            ->with('Horde_Core_Factory_HttpClient')
            ->willReturn($httpFactory);

        return $injector;
    }

    public function testConstructorSetsActiveForAdmin(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('isAdmin')
            ->willReturn(true);

        $GLOBALS['registry'] = $registry;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();

        $this->assertTrue($task->active);
    }

    public function testConstructorSetsInactiveForNonAdmin(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())
            ->method('isAdmin')
            ->willReturn(false);

        $GLOBALS['registry'] = $registry;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();

        $this->assertFalse($task->active);
    }

    public function testExecuteReturnsEarlyWhenCheckVersionsThrows(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAdmin')->willReturn(true);
        $registry->method('getVersion')->willReturn('1.0.0');

        $notification = $this->createMock(Horde_Notification_Handler::class);
        $notification->expects($this->never())
            ->method('push');

        $injector = $this->createMockInjectorThatThrows();

        $GLOBALS['registry'] = $registry;
        $GLOBALS['notification'] = $notification;
        $GLOBALS['injector'] = $injector;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();

        // Execute should return early when checkVersions throws
        $result = $task->execute();

        $this->assertNull($result);
    }

    public function testExecuteWithNoAppsSkipsPackageCheck(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAdmin')->willReturn(true);
        $registry->method('getVersion')->willReturn('1.0.0');
        $registry->method('listAllApps')->willReturn([]);

        $notification = $this->createMock(Horde_Notification_Handler::class);
        $notification->expects($this->never())
            ->method('push');

        $injector = $this->createMockInjectorWithEmptyVersions();

        $GLOBALS['registry'] = $registry;
        $GLOBALS['notification'] = $notification;
        $GLOBALS['injector'] = $injector;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();

        // Execute should not fail when no apps are installed
        $result = $task->execute();

        $this->assertNull($result);
    }

    public function testExecuteWithNoUpdatesAvailable(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAdmin')->willReturn(true);
        $registry->method('getVersion')
            ->willReturnCallback(function ($app) {
                return $app === 'horde' ? '1.0.0' : ($app === 'app1' ? '1.0.0' : '2.0.0');
            });
        $registry->method('listAllApps')->willReturn(['app1', 'app2']);

        $notification = $this->createMock(Horde_Notification_Handler::class);
        // No notifications should be pushed when no updates available
        $notification->expects($this->never())
            ->method('push');

        $injector = $this->createMockInjectorWithEmptyVersions();

        $GLOBALS['registry'] = $registry;
        $GLOBALS['notification'] = $notification;
        $GLOBALS['injector'] = $injector;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();
        $result = $task->execute();

        $this->assertNull($result);
    }

    public function testTaskHasCorrectInterval(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAdmin')->willReturn(true);

        $GLOBALS['registry'] = $registry;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();

        // Verify it runs weekly
        $this->assertEquals(
            constant('Horde_LoginTasks::WEEKLY'),
            $task->interval
        );
    }

    public function testTaskHasCorrectDisplayType(): void
    {
        $registry = $this->createMock(Horde_Registry::class);
        $registry->method('isAdmin')->willReturn(true);

        $GLOBALS['registry'] = $registry;

        $task = new Horde_LoginTasks_Task_UpgradeCheck();

        // Verify it displays nothing (runs silently)
        $this->assertEquals(
            constant('Horde_LoginTasks::DISPLAY_NONE'),
            $task->display
        );
    }
}
