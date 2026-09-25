<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @author    Torben Dannhauer <torben@dannhauer.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl LGPL-2
 * @package   Horde
 */

namespace Horde\Horde\Cli\ActiveSync;

use Horde\Cli\Cli;

final class Formatter
{
    public static function humanAge(?int $seconds): string
    {
        if ($seconds === null) {
            return 'n/a';
        }

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            return sprintf('%dm%02ds', intdiv($seconds, 60), $seconds % 60);
        }

        if ($seconds < 86400) {
            return sprintf('%dh%02dm', intdiv($seconds, 3600), intdiv($seconds % 3600, 60));
        }

        return intdiv($seconds, 86400) . 'd';
    }

    public static function colorStatus(Cli $cli, string $status): string
    {
        return match (trim($status)) {
            'ok' => $cli->green($status),
            'warn' => $cli->yellow($status),
            'critical' => $cli->red($status),
            default => $status,
        };
    }

    public static function json(array $data): string
    {
        $json = json_encode(
            $data,
            JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES
        );

        return $json === false ? '{}' : $json;
    }
}
