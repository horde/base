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

namespace Horde\Horde\Test\Unit\Cli\ActiveSync;

use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Horde\Cli\ActiveSync\Formatter;
use PHPUnit\Framework\TestCase;

final class FormatterTest extends TestCase
{
    protected function setUp(): void
    {
        if (!class_exists(SnapshotService::class)) {
            self::markTestSkipped('The ActiveSync snapshot service is unavailable.');
        }
    }

    /**
     * @dataProvider humanAgeProvider
     */
    public function testHumanAge(?int $seconds, string $expected): void
    {
        self::assertSame($expected, Formatter::humanAge($seconds));
    }

    public static function humanAgeProvider(): array
    {
        return [
            'unknown' => [null, 'n/a'],
            'seconds' => [42, '42s'],
            'minutes' => [192, '3m12s'],
            'hours' => [7500, '2h05m'],
            'days' => [259200, '3d'],
        ];
    }

    public function testJsonIsPrettyPrintedAndLeavesSlashesUnescaped(): void
    {
        $json = Formatter::json(['path' => '/var/log/device', 'critical' => 1]);

        self::assertStringContainsString("\n", $json);
        self::assertStringContainsString('"/var/log/device"', $json);
        self::assertStringContainsString('"critical": 1', $json);
    }
}
