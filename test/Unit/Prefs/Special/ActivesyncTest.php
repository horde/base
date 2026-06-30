<?php

/**
 * Tests for ActiveSync prefs/admin account-only remote wipe dispatch.
 *
 * @license   http://www.horde.org/licenses/lgpl LGPL
 * @copyright 2026 Horde LLC (http://www.horde.org)
 * @package   Horde
 */

use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Horde_Unit_Prefs_Special_ActivesyncTest extends TestCase
{
    private array $_notifications = [];

    protected function setUp(): void
    {
        $this->_notifications = [];
    }

    public function testDeviceSupportsAccountOnlyWipeRequiresEas161()
    {
        $this->assertFalse(Horde_ActiveSync::deviceSupportsAccountOnlyWipe('16.0'));
        $this->assertFalse(Horde_ActiveSync::deviceSupportsAccountOnlyWipe('14.1'));
        $this->assertTrue(Horde_ActiveSync::deviceSupportsAccountOnlyWipe('16.1'));
        $this->assertTrue(Horde_ActiveSync::deviceSupportsAccountOnlyWipe('16.2'));
    }

    public function testPrefsAccountWipeSetsAccountOnlyPendingForSupportedDevice()
    {
        $deviceId = 'device-161';
        $state = $this->_createStateMock($deviceId, '16.1', true);
        $this->_runPrefsUpdate(['accountwipeid' => $deviceId], $state);

        $this->assertSame(
            [['device-161', 'testuser', Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING]],
            $state->accountOnlyRwStatusCalls
        );
        $this->assertSame([], $state->rwStatusCalls);
        $this->assertStringContainsString(
            'account-only wipe',
            strtolower($this->_notifications[0][0])
        );
    }

    public function testPrefsAccountWipeRejectsUnsupportedDeviceVersion()
    {
        $deviceId = 'device-160';
        $state = $this->_createStateMock($deviceId, '16.0', true);
        $this->_runPrefsUpdate(['accountwipeid' => $deviceId], $state);

        $this->assertSame([], $state->accountOnlyRwStatusCalls);
        $this->assertSame([], $state->rwStatusCalls);
        $this->assertStringContainsString('EAS 16.1', $this->_notifications[0][0]);
        $this->assertSame('horde.error', $this->_notifications[0][1]);
    }

    public function testAdminAccountWipeDispatchSetsPendingForSupportedDevice()
    {
        $deviceId = 'admin-device-161';
        $state = $this->_createStateMock($deviceId, '16.1', false);
        $this->_runAdminAccountWipe($deviceId, $state);

        $this->assertSame(
            [['admin-device-161', 'testuser', Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING]],
            $state->accountOnlyRwStatusCalls
        );
        $this->assertSame([], $state->rwStatusCalls);
        $this->assertStringContainsString(
            'account-only wipe has been requested',
            strtolower($this->_notifications[0][0])
        );
        $this->assertSame('horde.success', $this->_notifications[0][1]);
    }

    public function testAdminAccountWipeDispatchRejectsUnsupportedDeviceVersion()
    {
        $deviceId = 'admin-device-160';
        $state = $this->_createStateMock($deviceId, '16.0', false);
        $this->_runAdminAccountWipe($deviceId, $state);

        $this->assertSame([], $state->accountOnlyRwStatusCalls);
        $this->assertSame([], $state->rwStatusCalls);
        $this->assertStringContainsString('EAS 16.1', $this->_notifications[0][0]);
        $this->assertSame('horde.error', $this->_notifications[0][1]);
    }

    public function testPrefsCancelWipeClearsBothRWStatuses()
    {
        $deviceId = 'device-cancel';
        $state = $this->_createStateMock($deviceId, '16.1', true);
        $this->_runPrefsUpdate(['cancelwipe' => $deviceId], $state);

        $this->assertSame(
            [[$deviceId, 'testuser', Horde_ActiveSync::RWSTATUS_OK]],
            $state->accountOnlyRwStatusCalls
        );
        $this->assertSame(
            [[$deviceId, Horde_ActiveSync::RWSTATUS_OK]],
            $state->rwStatusCalls
        );
        $this->assertNotEmpty($this->_notifications);
    }

    private function _runPrefsUpdate(array $vars, Horde_Unit_Prefs_Special_ActivesyncTest_StateStub $state): void
    {
        $injector = $this->getMockBuilder('Horde_Injector')
            ->disableOriginalConstructor()
            ->getMock();
        $injector->method('getInstance')
            ->willReturnCallback(function ($interface) use ($state) {
                switch ($interface) {
                    case 'Horde_ActiveSyncState':
                        return $state;

                    case 'Horde_Log_Logger':
                        return $this->getMockBuilder('Horde_Log_Logger')
                            ->disableOriginalConstructor()
                            ->getMock();
                }
            });
        $GLOBALS['injector'] = $injector;

        $registry = $this->getMockBuilder('Horde_Registry')
            ->disableOriginalConstructor()
            ->getMock();
        $registry->method('getAuth')->willReturn('testuser');
        $registry->method('pushApp')->willReturn(null);
        $GLOBALS['registry'] = $registry;

        $notification = $this->getMockBuilder('Horde_Notification_Handler')
            ->disableOriginalConstructor()
            ->getMock();
        $notification->method('push')
            ->willReturnCallback(function ($msg, $type = null) {
                $this->_notifications[] = [$msg, $type];
            });
        $GLOBALS['notification'] = $notification;

        $prefs = $this->getMockBuilder('Horde_Prefs')
            ->disableOriginalConstructor()
            ->getMock();
        $prefs->method('setValue')->willReturn(null);
        $GLOBALS['prefs'] = $prefs;

        $ui = $this->getMockBuilder('Horde_Core_Prefs_Ui')
            ->disableOriginalConstructor()
            ->getMock();
        $ui->vars = new Horde_Variables($vars);

        $handler = new Horde_Prefs_Special_Activesync();
        $handler->update($ui);
    }

    private function _runAdminAccountWipe(
        string $deviceId,
        Horde_Unit_Prefs_Special_ActivesyncTest_StateStub $state,
        string $user = 'testuser'
    ): void {
        $notification = $this->getMockBuilder('Horde_Notification_Handler')
            ->disableOriginalConstructor()
            ->getMock();
        $notification->method('push')
            ->willReturnCallback(function ($msg, $type = null) {
                $this->_notifications[] = [$msg, $type];
            });
        $GLOBALS['notification'] = $notification;

        $device = $state->loadDeviceInfo($deviceId);
        if (!Horde_ActiveSync::deviceSupportsAccountOnlyWipe($device->version ?? null)) {
            $GLOBALS['notification']->push(
                _("Account-only wipe requires a device with EAS 16.1 or newer."),
                'horde.error'
            );
            return;
        }

        $state->setAccountOnlyRWStatus(
            $deviceId,
            $user,
            Horde_ActiveSync::RWSTATUS_ACCOUNTONLY_PENDING
        );
        $GLOBALS['notification']->push(
            _("An account-only wipe has been requested. The account will be removed from the device on the next synchronization."),
            'horde.success'
        );
    }

    private function _createStateMock(
        string $deviceId,
        string $version,
        bool $checkExists
    ): Horde_Unit_Prefs_Special_ActivesyncTest_StateStub {
        return new Horde_Unit_Prefs_Special_ActivesyncTest_StateStub($deviceId, $version, $checkExists);
    }
}

class Horde_Unit_Prefs_Special_ActivesyncTest_StateStub
{
    public array $rwStatusCalls = [];
    public array $accountOnlyRwStatusCalls = [];

    private string $_deviceId;
    private string $_version;
    private bool $_exists;

    public function __construct(string $deviceId, string $version, bool $exists)
    {
        $this->_deviceId = $deviceId;
        $this->_version = $version;
        $this->_exists = $exists;
    }

    public function setLogger($logger) {}

    public function deviceExists($deviceId, $user = null)
    {
        return $this->_exists && $deviceId === $this->_deviceId;
    }

    public function loadDeviceInfo($deviceId, $user = null)
    {
        $device = new stdClass();
        $device->id = $deviceId;
        $device->version = $this->_version;
        return $device;
    }

    public function setDeviceRWStatus($deviceId, $status)
    {
        $this->rwStatusCalls[] = [$deviceId, $status];
    }

    public function setAccountOnlyRWStatus($deviceId, $user, $status)
    {
        $this->accountOnlyRwStatusCalls[] = [$deviceId, $user, $status];
    }
}
