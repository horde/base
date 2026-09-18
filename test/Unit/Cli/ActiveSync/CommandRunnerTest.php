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
use Horde\Injector\Injector;
use Horde_ActiveSync_State_Base;
use Horde_Cli;
use Horde_Exception;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

final class CommandRunnerTest extends TestCase
{
    private array $output = [];

    protected function setUp(): void
    {
        $this->output = [];
        if (!class_exists(SnapshotService::class)) {
            self::markTestSkipped('The ActiveSync snapshot service is unavailable.');
        }
    }

    public function testNoArgumentsPrintsUsage(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(ExitCode::USAGE, $runner->run([]));
        self::assertStringContainsString('horde-activesync summary', implode("\n", $this->output));
    }

    public function testUnknownSubcommandReturnsUsageError(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(ExitCode::USAGE, $runner->run(['unknown']));
        self::assertStringContainsString('horde-activesync show', implode("\n", $this->output));
    }

    public function testSummaryJsonReturnsCriticalExitCode(): void
    {
        $runner = $this->runner($this->service(foldersyncRequired: 5));

        self::assertSame(
            ExitCode::CRITICAL,
            $runner->run(['summary', '--format=json'])
        );
        self::assertStringContainsString('"critical": 1', implode("\n", $this->output));
    }

    public function testSummaryWithoutCriticalDevicesReturnsOk(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(
            ExitCode::OK,
            $runner->run(['summary', '--format=json'])
        );
    }

    public function testUnavailableServiceReturnsUnavailable(): void
    {
        $cli = $this->cli();
        $injector = $this->createMock(Injector::class);
        $injector->method('get')->willThrowException(
            new Horde_Exception('ActiveSync is disabled.')
        );
        $runner = new CommandRunner($cli, $injector);

        self::assertSame(ExitCode::UNAVAILABLE, $runner->run(['summary']));
    }

    public function testShowJsonReturnsDevice(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(
            ExitCode::OK,
            $runner->run([
                'show',
                'alice@example.com',
                'DEVICE1',
                '--format=json',
            ])
        );
        self::assertStringContainsString('"deviceId": "DEVICE1"', implode("\n", $this->output));
    }

    public function testShowMissingArgumentsReturnsUsageError(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(ExitCode::USAGE, $runner->run(['show']));
    }

    public function testShowMissingDeviceReturnsOne(): void
    {
        $runner = $this->runner($this->service(includeDevice: false));

        self::assertSame(
            ExitCode::CRITICAL,
            $runner->run(['show', 'alice@example.com', 'MISSING'])
        );
    }

    public function testInvalidHealthReturnsUsageError(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(
            ExitCode::USAGE,
            $runner->run(['summary', '--health=bogus'])
        );
    }

    public function testTopDispatchesToViewer(): void
    {
        $runner = $this->runner($this->service());

        self::assertSame(
            ExitCode::OK,
            $runner->run(['top', '--format=json'])
        );
        self::assertStringContainsString('"devices"', implode("\n", $this->output));
    }

    private function runner(SnapshotService $service): CommandRunner
    {
        $cli = $this->cli();
        $injector = $this->createMock(Injector::class);
        $injector->method('get')->with(SnapshotService::class)->willReturn($service);

        return new CommandRunner($cli, $injector);
    }

    private function cli(): Horde_Cli&MockObject
    {
        $cli = $this->createMock(Horde_Cli::class);
        $cli->method('writeln')->willReturnCallback(
            function (string $text = ''): void {
                $this->output[] = $text;
            }
        );
        $cli->method('red')->willReturnCallback(
            static fn (string $text): string => $text
        );

        return $cli;
    }

    private function service(
        int $foldersyncRequired = 0,
        bool $includeDevice = true
    ): SnapshotService {
        $state = $this->getMockBuilder(Horde_ActiveSync_State_Base::class)
            ->disableOriginalConstructor()
            ->getMockForAbstractClass();
        $row = [
            'device_user' => 'alice@example.com',
            'device_id' => 'DEVICE1',
            'device_type' => 'iPhone',
            'device_properties' => ['version' => '16.1'],
            'device_rwstatus' => 0,
            'device_accountonly_rwstatus' => 0,
        ];
        $cache = [
            'timestamp' => 1699999990,
            'hbinterval' => 900,
            'foldersyncrequired' => $foldersyncRequired,
            'collections' => [],
        ];

        $state->method('listDevices')->willReturn($includeDevice ? [$row] : []);
        $state->method('getSyncCache')->willReturn($cache);
        $state->method('getLastSyncTimestamp')->willReturn(1699999990);

        return new SnapshotService(
            $state,
            new DeviceLogPathResolver(null, null),
            clock: static fn (): int => 1700000000
        );
    }
}
