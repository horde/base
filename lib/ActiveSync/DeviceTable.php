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
                    'health' => $row['health'] ?? null,
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
                    'health' => $row['health'] ?? null,
                ];
            }
        }

        return $entries;
    }

    /**
     * Return the status badge class for a health status.
     */
    public static function healthBadgeClass(string $status): string
    {
        return match ($status) {
            'ok' => 'settings-status-ok',
            'warn' => 'settings-status-warning',
            'critical' => 'settings-status-error',
            default => 'settings-status-na',
        };
    }

    /**
     * Return a translated label for a health signal.
     */
    public static function signalLabel(string $code): string
    {
        return match ($code) {
            'hb_in_flight' => _("Heartbeat in flight"),
            'hb_stuck' => _("Heartbeat stuck"),
            'hb_missing_end' => _("Heartbeat never ended"),
            'hb_abandoned' => _("Heartbeat abandoned"),
            'fsr_warn' => _("FolderSync loop (warning)"),
            'fsr_critical' => _("FolderSync loop (critical)"),
            'blocked' => _("Blocked"),
            'wipe_pending' => _("Wipe pending"),
            'wipe_complete' => _("Wiped"),
            'backlog_pending' => _("Backlog pending"),
            'backlog_stuck' => _("Backlog stuck"),
            default => $code,
        };
    }

    /**
     * Format an age in a compact human-readable form.
     */
    public static function humanAge(?int $seconds): string
    {
        if ($seconds === null) {
            return _("Never");
        }
        if ($seconds < 60) {
            return sprintf(_("%ds"), $seconds);
        }
        if ($seconds < 3600) {
            return sprintf(_("%dm"), intdiv($seconds, 60));
        }
        if ($seconds < 86400) {
            return sprintf(_("%dh"), intdiv($seconds, 3600));
        }

        return sprintf(_("%dd"), intdiv($seconds, 86400));
    }
}
