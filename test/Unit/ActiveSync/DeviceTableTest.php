<?php

/**
 * Tests for ActiveSync device table sorting/grouping helpers.
 *
 * @license   http://www.horde.org/licenses/lgpl LGPL
 * @copyright 2026 Horde LLC (http://www.horde.org/)
 * @package   Horde
 */

use Horde\ActiveSync\Ops\CollectionHealth;
use Horde\ActiveSync\Ops\DeviceHealth;
use Horde\ActiveSync\Ops\HealthSignal;
use Horde\ActiveSync\Ops\SignalCode;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class Horde_Unit_ActiveSync_DeviceTableTest extends TestCase
{
    public function testSortRowsByUserThenDeviceType()
    {
        $rows = [
            $this->_row('bob', 'iPhone', 'b1'),
            $this->_row('alice', 'iPad', 'a1'),
            $this->_row('alice', 'iPhone', 'a2'),
        ];

        $sorted = Horde_ActiveSync_DeviceTable::sortRows($rows, true);

        $this->assertSame('alice', $sorted[0]['device']->user);
        $this->assertSame('iPad', $sorted[0]['device']->deviceType);
        $this->assertSame('alice', $sorted[1]['device']->user);
        $this->assertSame('iPhone', $sorted[1]['device']->deviceType);
        $this->assertSame('bob', $sorted[2]['device']->user);
    }

    public function testToEntriesGroupsByUser()
    {
        $rows = Horde_ActiveSync_DeviceTable::sortRows([
            $this->_row('bob', 'iPhone', 'b1'),
            $this->_row('alice', 'iPhone', 'a1'),
            $this->_row('alice', 'iPad', 'a2'),
        ], true);

        $entries = Horde_ActiveSync_DeviceTable::toEntries($rows, true);

        $this->assertSame('header', $entries[0]['type']);
        $this->assertSame('alice', $entries[0]['user']);
        $this->assertSame(2, $entries[0]['count']);
        $this->assertSame('device', $entries[1]['type']);
        $this->assertSame('iPad', $entries[1]['device']->deviceType);
        $this->assertSame('device', $entries[2]['type']);
        $this->assertSame('iPhone', $entries[2]['device']->deviceType);
        $this->assertSame('header', $entries[3]['type']);
        $this->assertSame('bob', $entries[3]['user']);
        $this->assertSame(1, $entries[3]['count']);
    }

    public function testToEntriesWithoutGrouping()
    {
        $rows = [
            $this->_row('alice', 'iPhone', 'a1'),
        ];

        $entries = Horde_ActiveSync_DeviceTable::toEntries($rows, false);

        $this->assertCount(1, $entries);
        $this->assertSame('device', $entries[0]['type']);
        $this->assertArrayNotHasKey('user', $entries[0]);
        $this->assertNull($entries[0]['health']);
    }

    public function testToEntriesPassesHealthThrough()
    {
        if (!class_exists(DeviceHealth::class)) {
            self::markTestSkipped('ActiveSync device health is unavailable.');
        }

        $health = new DeviceHealth(
            user: 'alice@example.com',
            deviceId: 'DEVICE1',
            deviceType: 'iPhone',
            version: '16.1',
            status: 'warn',
            active: true,
            ageSeconds: 90,
            hbinterval: 900,
            foldersyncrequired: 1,
            signals: [
                new HealthSignal('blocked', 'warn', 'Device is blocked.'),
            ],
            collections: [
                new CollectionHealth(
                    'collection1',
                    'Email',
                    'server1',
                    '1',
                    'ok',
                    []
                ),
            ]
        );
        $row = $this->_row('alice@example.com', 'iPhone', 'DEVICE1');
        $row['health'] = $health;

        $ungrouped = Horde_ActiveSync_DeviceTable::toEntries([$row], false);
        $grouped = Horde_ActiveSync_DeviceTable::toEntries([$row], true);

        $this->assertSame($health, $ungrouped[0]['health']);
        $this->assertSame($health, $grouped[1]['health']);
    }

    public function testHealthBadgeClass()
    {
        $this->assertSame(
            'settings-status-ok',
            Horde_ActiveSync_DeviceTable::healthBadgeClass('ok')
        );
        $this->assertSame(
            'settings-status-warning',
            Horde_ActiveSync_DeviceTable::healthBadgeClass('warn')
        );
        $this->assertSame(
            'settings-status-error',
            Horde_ActiveSync_DeviceTable::healthBadgeClass('critical')
        );
        $this->assertSame(
            'settings-status-na',
            Horde_ActiveSync_DeviceTable::healthBadgeClass('unknown')
        );
    }

    public function testSignalLabelsCoverEverySignalCode()
    {
        $labels = [
            SignalCode::HB_IN_FLIGHT => _("Heartbeat in flight"),
            SignalCode::HB_STUCK => _("Heartbeat stuck"),
            SignalCode::HB_MISSING_END => _("Heartbeat never ended"),
            SignalCode::HB_ABANDONED => _("Heartbeat abandoned"),
            SignalCode::FSR_WARN => _("FolderSync loop (warning)"),
            SignalCode::FSR_CRITICAL => _("FolderSync loop (critical)"),
            SignalCode::BLOCKED => _("Blocked"),
            SignalCode::WIPE_PENDING => _("Wipe pending"),
            SignalCode::WIPE_COMPLETE => _("Wiped"),
            SignalCode::BACKLOG_PENDING => _("Backlog pending"),
            SignalCode::BACKLOG_STUCK => _("Backlog stuck"),
        ];

        $this->assertSame(SignalCode::all(), array_keys($labels));
        foreach ($labels as $code => $label) {
            $this->assertSame(
                $label,
                Horde_ActiveSync_DeviceTable::signalLabel($code)
            );
        }
    }

    public function testHumanAge()
    {
        $this->assertSame(_("Never"), Horde_ActiveSync_DeviceTable::humanAge(null));
        $this->assertSame('59s', Horde_ActiveSync_DeviceTable::humanAge(59));
        $this->assertSame('2m', Horde_ActiveSync_DeviceTable::humanAge(120));
        $this->assertSame('3h', Horde_ActiveSync_DeviceTable::humanAge(10800));
        $this->assertSame('4d', Horde_ActiveSync_DeviceTable::humanAge(345600));
    }

    private function _row(string $user, string $type, string $id): array
    {
        $device = new stdClass();
        $device->user = $user;
        $device->deviceType = $type;
        $device->id = $id;

        return [
            'device' => $device,
            'collections' => [],
        ];
    }
}
