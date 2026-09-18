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

use Horde\Core\ActiveSync\Ops\DeviceLogPathResolver;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Horde\Cli\ActiveSync\CommandRunner;
use Horde\Horde\Cli\ActiveSync\ExitCode;
use Horde\Horde\Cli\ActiveSync\TopCommand;
use Horde\Injector\Injector;
use Horde_ActiveSync_State_Sql;
use Horde_Cli;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class TopCommandTest extends TestCase
{
    private array $output = [];

    protected function setUp(): void
    {
        $this->output = [];
        if (!class_exists(SnapshotService::class)) {
            self::markTestSkipped('The ActiveSync snapshot service is unavailable.');
        }
    }

    public function testOneShotTableRendersSummaryHeaderAndDevices(): void
    {
        $command = new TopCommand(
            $this->cli(),
            $this->service(),
            clock: static fn (): int => 1700000000
        );

        self::assertSame(ExitCode::OK, $command->run([]));
        $output = implode("\n", $this->output);
        self::assertStringContainsString('devices=2 active=2 ok=1 warn=0 critical=1', $output);
        self::assertStringContainsString('USER', $output);
        self::assertStringContainsString('DEVICE', $output);
        self::assertStringContainsString('DEVICE1', $output);
        self::assertStringContainsString('DEVICE2', $output);
    }

    public function testJsonContainsSummaryAndDevices(): void
    {
        $command = new TopCommand($this->cli(), $this->service());

        self::assertSame(ExitCode::OK, $command->run(['--format=json']));
        $decoded = json_decode(implode("\n", $this->output), true, 512, JSON_THROW_ON_ERROR);
        self::assertArrayHasKey('summary', $decoded);
        self::assertArrayHasKey('devices', $decoded);
    }

    public function testWatchRendersTwiceAndSleepsBetweenIterations(): void
    {
        $sleeps = 0;
        $command = new TopCommand(
            $this->cli(),
            $this->service(),
            static function (int $interval) use (&$sleeps): void {
                self::assertSame(0, $interval);
                ++$sleeps;
            }
        );

        self::assertSame(
            ExitCode::OK,
            $command->run(['--watch', '--iterations=2', '--interval=0'])
        );
        self::assertSame(2, $this->countOutputLinesStartingWith('devices='));
        self::assertSame(1, $sleeps);
    }

    public function testJsonIgnoresWatchAndRendersOnce(): void
    {
        $sleeps = 0;
        $command = new TopCommand(
            $this->cli(),
            $this->service(),
            static function () use (&$sleeps): void {
                ++$sleeps;
            }
        );

        self::assertSame(
            ExitCode::OK,
            $command->run(['--watch', '--format=json'])
        );
        self::assertCount(1, $this->output);
        self::assertSame(0, $sleeps);
    }

    public function testInvalidSortReturnsUsageThroughCommandRunner(): void
    {
        $injector = $this->createMock(Injector::class);
        $injector->method('get')->with(SnapshotService::class)->willReturn($this->service());
        $runner = new CommandRunner($this->cli(), $injector);

        self::assertSame(
            ExitCode::USAGE,
            $runner->run(['top', '--sort=bogus'])
        );
    }

    public function testStuckOnlyFiltersHealthyDevice(): void
    {
        $command = new TopCommand($this->cli(), $this->service());

        self::assertSame(ExitCode::OK, $command->run(['--stuck-only']));
        $output = implode("\n", $this->output);
        self::assertStringContainsString('DEVICE2', $output);
        self::assertStringNotContainsString('DEVICE1', $output);
    }

    public function testLongDeviceIdIsTruncatedWithEllipsis(): void
    {
        $deviceId = str_repeat('A', 40);
        $command = new TopCommand($this->cli(), $this->service([$this->row($deviceId)]));

        self::assertSame(ExitCode::OK, $command->run([]));
        $output = implode("\n", $this->output);
        self::assertStringContainsString(str_repeat('A', 31) . '…', $output);
        self::assertStringNotContainsString($deviceId, $output);
    }

    private function countOutputLinesStartingWith(string $prefix): int
    {
        return count(array_filter(
            $this->output,
            static fn (string $line): bool => str_starts_with($line, $prefix)
        ));
    }

    private function cli(): Horde_Cli&MockObject
    {
        $cli = $this->createMock(Horde_Cli::class);
        $cli->method('writeln')->willReturnCallback(
            function (string $text = ''): void {
                $this->output[] = $text;
            }
        );
        foreach (['red', 'green', 'yellow'] as $method) {
            $cli->method($method)->willReturnCallback(
                static fn (string $text): string => $text
            );
        }

        return $cli;
    }

    /**
     * @param array<int, array<string, mixed>>|null $rows
     */
    private function service(?array $rows = null): SnapshotService
    {
        $rows ??= [
            $this->row('DEVICE1'),
            $this->row('DEVICE2'),
        ];
        $state = $this->getMockBuilder(Horde_ActiveSync_State_Sql::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['listDevices', 'getSyncCache'])
            ->getMock();
        $state->method('listDevices')->willReturn($rows);
        $state->method('getSyncCache')->willReturnCallback(
            static fn (string $deviceId): array => [
                'timestamp' => 1699999990,
                'hbinterval' => 900,
                'foldersyncrequired' => $deviceId === 'DEVICE2' ? 5 : 0,
                'collections' => [],
            ]
        );

        return new SnapshotService(
            $state,
            new DeviceLogPathResolver(null, null),
            clock: static fn (): int => 1700000000
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function row(string $deviceId): array
    {
        return [
            'device_user' => 'alice@example.com',
            'device_id' => $deviceId,
            'device_type' => 'iPhone',
            'device_properties' => ['version' => '16.1'],
            'device_rwstatus' => 0,
            'device_accountonly_rwstatus' => 0,
        ];
    }
}
