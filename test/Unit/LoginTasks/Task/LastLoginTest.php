<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

namespace Horde\Horde\Unit\LoginTasks\Task;

use Horde_Injector;
use Horde_LoginTasks_Task_LastLogin;
use Horde_Notification_Handler;
use Horde_Prefs;
use Horde_Registry;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests for LastLogin login task.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */
#[CoversClass(Horde_LoginTasks_Task_LastLogin::class)]
class LastLoginTest extends TestCase
{
    private array $originalGlobals = [];

    private ?string $originalRemoteAddr = null;

    protected function setUp(): void
    {
        $this->originalGlobals = [
            'registry' => $GLOBALS['registry'] ?? null,
            'notification' => $GLOBALS['notification'] ?? null,
            'injector' => $GLOBALS['injector'] ?? null,
            'prefs' => $GLOBALS['prefs'] ?? null,
        ];
        $this->originalRemoteAddr = $_SERVER['REMOTE_ADDR'] ?? null;
        // Provide a deterministic remote address for the PTR fallback.
        $_SERVER['REMOTE_ADDR'] = '127.0.0.1';
        unset($_SERVER['HTTP_X_FORWARDED_FOR']);
    }

    protected function tearDown(): void
    {
        foreach ($this->originalGlobals as $key => $value) {
            if ($value === null) {
                unset($GLOBALS[$key]);
            } else {
                $GLOBALS[$key] = $value;
            }
        }
        if ($this->originalRemoteAddr === null) {
            unset($_SERVER['REMOTE_ADDR']);
        } else {
            $_SERVER['REMOTE_ADDR'] = $this->originalRemoteAddr;
        }
    }

    /**
     * Build an injector whose Net_DNS2_Resolver instance is null and force the
     * safe gethostbyaddr() fallback so the test does not hit the network.
     */
    private function createInjectorWithoutResolver(): Horde_Injector
    {
        $injector = $this->createMock(Horde_Injector::class);
        $injector->method('getInstance')
            ->with('Net_DNS2_Resolver')
            ->willReturn(null);

        return $injector;
    }

    /**
     * A stored last_login whose 'host' was a serialized object is restored as
     * a __PHP_Incomplete_Class.
     * Casting that to string throws a fatal, un-suppressable Error. The task
     * must not crash and must still record the new login time (see horde/base#171).
     */
    public function testExecuteDoesNotCrashOnStaleObjectHost(): void
    {
        // Serialize an array whose host is an object, mimicking historical
        // Net_DNS2 domain data written by older Horde versions.
        $legacy = serialize([
            'host' => (object) ['name' => 'old.example.com'],
            'time' => 1700000000,
        ]);

        $captured = null;

        $prefs = $this->createMock(Horde_Prefs::class);
        $prefs->method('getValue')
            ->willReturnCallback(static function ($pref) use ($legacy) {
                return match ($pref) {
                    'last_login' => $legacy,
                    'show_last_login' => false,
                    default => null,
                };
            });
        $prefs->expects($this->once())
            ->method('setValue')
            ->with(
                'last_login',
                $this->callback(static function ($value) use (&$captured) {
                    $captured = @unserialize($value, ['allowed_classes' => false]);

                    return true;
                })
            );

        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())->method('setTimeZone');

        $GLOBALS['prefs'] = $prefs;
        $GLOBALS['registry'] = $registry;
        $GLOBALS['injector'] = $this->createInjectorWithoutResolver();
        $GLOBALS['notification'] = $this->createMock(Horde_Notification_Handler::class);

        $task = new Horde_LoginTasks_Task_LastLogin();

        $task->execute();

        $this->assertIsArray($captured);
        $this->assertIsString($captured['host']);
        $this->assertIsInt($captured['time']);
    }

    /**
     * A completely unreadable last_login pref (unserialize returns false) must
     * not crash either. Login proceeds and a fresh record is written.
     */
    public function testExecuteHandlesUnreadablePref(): void
    {
        $prefs = $this->createMock(Horde_Prefs::class);
        $prefs->method('getValue')
            ->willReturnCallback(static function ($pref) {
                return match ($pref) {
                    'last_login' => 'not-valid-serialized-data',
                    'show_last_login' => false,
                    default => null,
                };
            });
        $prefs->expects($this->once())->method('setValue');

        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())->method('setTimeZone');

        $GLOBALS['prefs'] = $prefs;
        $GLOBALS['registry'] = $registry;
        $GLOBALS['injector'] = $this->createInjectorWithoutResolver();
        $GLOBALS['notification'] = $this->createMock(Horde_Notification_Handler::class);

        $task = new Horde_LoginTasks_Task_LastLogin();
        $task->execute();

        $this->assertTrue(true);
    }

    /**
     * A valid string host is preserved through the normalization unchanged.
     */
    public function testExecutePreservesValidStringHost(): void
    {
        $legacy = serialize([
            'host' => 'real.example.com',
            'time' => 1700000000,
        ]);

        $notification = $this->createMock(Horde_Notification_Handler::class);
        $notification->expects($this->once())
            ->method('push')
            ->with(
                $this->stringContains('real.example.com'),
                'horde.message'
            );

        $prefs = $this->createMock(Horde_Prefs::class);
        $prefs->method('getValue')
            ->willReturnCallback(static function ($pref) use ($legacy) {
                return match ($pref) {
                    'last_login' => $legacy,
                    'show_last_login' => true,
                    'date_format' => 'short',
                    'time_format' => 'medium-time',
                    default => null,
                };
            });
        $prefs->expects($this->once())->method('setValue');

        $registry = $this->createMock(Horde_Registry::class);
        $registry->expects($this->once())->method('setTimeZone');

        $GLOBALS['prefs'] = $prefs;
        $GLOBALS['registry'] = $registry;
        $GLOBALS['injector'] = $this->createInjectorWithoutResolver();
        $GLOBALS['notification'] = $notification;

        $task = new Horde_LoginTasks_Task_LastLogin();
        $task->execute();
    }
}
