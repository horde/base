<?php

/**
 * Helpers for rendering ActiveSync device tables.
 *
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 * @package  Horde
 */

/**
 * Build sorted, optionally grouped device table entries for templates.
 */
class Horde_ActiveSync_DeviceTable
{
    /**
     * Sort device rows.
     *
     * @param array<int, array{device: Horde_ActiveSync_Device, collections: array}> $rows
     * @param bool $byUser  If true, sort by username first.
     */
    public static function sortRows(array $rows, bool $byUser = true): array
    {
        usort(
            $rows,
            function (array $a, array $b) use ($byUser) {
                if ($byUser) {
                    $cmp = strcasecmp($a['device']->user, $b['device']->user);
                    if ($cmp !== 0) {
                        return $cmp;
                    }
                }

                $cmp = strcasecmp($a['device']->deviceType, $b['device']->deviceType);
                if ($cmp !== 0) {
                    return $cmp;
                }

                return strcasecmp($a['device']->id, $b['device']->id);
            }
        );

        return $rows;
    }

    /**
     * Convert rows to template entries, optionally with user group headers.
     *
     * @param array<int, array{device: Horde_ActiveSync_Device, collections: array}> $rows
     *
     * @return array<int, array<string, mixed>>
     */
    public static function toEntries(array $rows, bool $groupByUser = false): array
    {
        if (!$groupByUser) {
            $entries = [];
            foreach ($rows as $row) {
                $entries[] = [
                    'type' => 'device',
                    'device' => $row['device'],
                    'collections' => $row['collections'],
                ];
            }

            return $entries;
        }

        $groups = [];
        foreach ($rows as $row) {
            $groups[$row['device']->user][] = $row;
        }
        ksort($groups, SORT_NATURAL | SORT_FLAG_CASE);

        $entries = [];
        foreach ($groups as $user => $userRows) {
            $entries[] = [
                'type' => 'header',
                'user' => $user,
                'count' => count($userRows),
            ];
            foreach ($userRows as $row) {
                $entries[] = [
                    'type' => 'device',
                    'device' => $row['device'],
                    'collections' => $row['collections'],
                ];
            }
        }

        return $entries;
    }
}
