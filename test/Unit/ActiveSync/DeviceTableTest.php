<?php

/**
 * Tests for ActiveSync device table sorting/grouping helpers.
 *
 * @license   http://www.horde.org/licenses/lgpl LGPL
 * @copyright 2026 Horde LLC (http://www.horde.org/)
 * @package   Horde
 */

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
