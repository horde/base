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

use Closure;
use Horde\ActiveSync\Ops\DeviceHealth;
use Horde\ActiveSync\Ops\SignalCode;
use Horde\Core\ActiveSync\Ops\FleetSnapshot;
use Horde\Core\ActiveSync\Ops\SnapshotCriteria;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Util\HordeString;
use Horde_Argv_Parser;
use Horde_Cli;
use Horde_Exception;
use InvalidArgumentException;

final class TopCommand
{
    private readonly Closure $sleep;
    private readonly Closure $clock;

    public function __construct(
        private readonly Horde_Cli $cli,
        private readonly SnapshotService $service,
        ?callable $sleep = null,
        ?callable $clock = null
    ) {
        $this->sleep = Closure::fromCallable($sleep ?? sleep(...));
        $this->clock = Closure::fromCallable($clock ?? time(...));
    }

    public function run(array $argv): int
    {
        $argv = $this->normalizeWatchOption($argv);
        $parser = new Horde_Argv_Parser(['addHelpOption' => false]);
        $parser->addOption('--watch', [
            'action' => 'store_true',
            'dest' => 'watch',
            'default' => false,
        ]);
        $parser->addOption('--interval', [
            'dest' => 'interval',
            'type' => 'int',
            'default' => 2,
        ]);
        $parser->addOption('--active-within', [
            'dest' => 'activeWithin',
            'type' => 'int',
            'default' => null,
        ]);
        $parser->addOption('--user', [
            'dest' => 'user',
            'default' => null,
        ]);
        $parser->addOption('--device', [
            'dest' => 'device',
            'default' => null,
        ]);
        $parser->addOption('--health', [
            'dest' => 'health',
            'default' => null,
        ]);
        $parser->addOption('--stuck-only', [
            'action' => 'store_true',
            'dest' => 'stuckOnly',
            'default' => false,
        ]);
        $parser->addOption('--sort', [
            'dest' => 'sort',
            'default' => SnapshotCriteria::SORT_HEALTH,
        ]);
        $parser->addOption('--format', [
            'dest' => 'format',
            'default' => 'table',
        ]);
        $parser->addOption('--limit', [
            'dest' => 'limit',
            'type' => 'int',
            'default' => 50,
        ]);
        $parser->addOption('--iterations', [
            'dest' => 'iterations',
            'type' => 'int',
            'default' => 0,
        ]);

        [$values, $args] = $parser->parseArgs($argv);
        if ($args !== []) {
            throw new InvalidArgumentException('Top does not accept positional arguments.');
        }
        if (!in_array($values->format, ['table', 'json'], true)) {
            throw new InvalidArgumentException('Format must be table or json.');
        }
        if ($values->interval < 0) {
            throw new InvalidArgumentException('Watch interval cannot be negative.');
        }
        if ($values->iterations < 0) {
            throw new InvalidArgumentException('Iterations cannot be negative.');
        }

        $criteria = new SnapshotCriteria(
            user: $values->user,
            deviceId: $values->device,
            activeWithin: $values->activeWithin,
            healthMin: $values->health,
            stuckOnly: $values->stuckOnly,
            limit: $values->limit,
            sort: $values->sort
        );

        $watch = $values->watch && $values->format !== 'json';
        $rendered = 0;
        do {
            if ($watch) {
                $this->clearForWatch();
            }

            try {
                $snapshot = $this->service->fleet($criteria);
            } catch (Horde_Exception $e) {
                $this->cli->message(
                    $this->cli->red('ActiveSync is unavailable: ' . $e->getMessage()),
                    'cli.error'
                );
                return ExitCode::UNAVAILABLE;
            }

            if ($values->format === 'json') {
                $this->cli->writeln(Formatter::json($snapshot->toArray()));
            } else {
                $this->renderTable($snapshot);
            }

            ++$rendered;
            if (!$watch || ($values->iterations > 0 && $rendered >= $values->iterations)) {
                break;
            }
            ($this->sleep)($values->interval);
        } while (true);

        return ExitCode::OK;
    }

    private function normalizeWatchOption(array $argv): array
    {
        $normalized = [];
        foreach ($argv as $arg) {
            if (preg_match('/^--watch=(\d+)$/', $arg, $matches)) {
                $normalized[] = '--watch';
                $normalized[] = '--interval=' . $matches[1];
                continue;
            }
            $normalized[] = $arg;
        }

        return $normalized;
    }

    private function clearForWatch(): void
    {
        if (function_exists('posix_isatty') && posix_isatty(STDOUT)) {
            $this->cli->writeln("\033[H\033[J");
            return;
        }

        $this->cli->writeln();
    }

    private function renderTable(FleetSnapshot $snapshot): void
    {
        $summary = $snapshot->summary;
        $shown = count($snapshot->devices);
        $line = sprintf(
            'devices=%d active=%d ok=%d warn=%d critical=%d stuck=%d wipe_pending=%d blocked=%d  as of %s',
            $summary->devices,
            $summary->active,
            $summary->ok,
            $summary->warn,
            $summary->critical,
            $summary->stuck,
            $summary->wipePending,
            $summary->blocked,
            date('H:i:s', ($this->clock)())
        );
        if ($shown < $summary->devices) {
            $line .= sprintf(' (showing %d of %d)', $shown, $summary->devices);
        }
        $this->cli->writeln($line);

        $rows = array_map($this->row(...), $snapshot->devices);
        $widths = [
            'user' => 8,
            'device' => 12,
            'type' => 6,
            'version' => 4,
            'age' => 6,
            'heartbeat' => 5,
            'fsr' => 3,
            'health' => 8,
            'signals' => 7,
        ];
        foreach ($rows as $row) {
            foreach ($widths as $column => $width) {
                $widths[$column] = max($width, HordeString::length($row[$column]));
            }
        }

        $this->cli->writeln($this->formatRow([
            'user' => 'USER',
            'device' => 'DEVICE',
            'type' => 'TYPE',
            'version' => 'VER',
            'age' => 'AGE',
            'heartbeat' => 'HB',
            'fsr' => 'FSR',
            'health' => 'HEALTH',
            'signals' => 'SIGNALS',
        ], $widths));

        foreach ($rows as $row) {
            $health = $this->pad($row['health'], $widths['health']);
            $row['health'] = Formatter::colorStatus($this->cli, $health);
            $this->cli->writeln($this->formatRow($row, $widths, false));
        }
    }

    private function row(DeviceHealth $device): array
    {
        $signals = array_values(array_diff($device->signalCodes(), [
            SignalCode::HB_IN_FLIGHT,
            SignalCode::HB_ABANDONED,
        ]));

        return [
            'user' => $this->truncate($device->user, 28),
            'device' => $this->truncate($device->deviceId, 32),
            'type' => $device->deviceType,
            'version' => $device->version ?? '-',
            'age' => Formatter::humanAge($device->ageSeconds),
            'heartbeat' => $device->hbinterval === null ? '-' : (string) $device->hbinterval,
            'fsr' => (string) $device->foldersyncrequired,
            'health' => $device->status,
            'signals' => $signals === [] ? '-' : implode(',', $signals),
        ];
    }

    private function formatRow(array $row, array $widths, bool $padHealth = true): string
    {
        $columns = [];
        foreach ($widths as $column => $width) {
            if ($column === 'health' && !$padHealth) {
                $columns[] = $row[$column];
            } else {
                $columns[] = $this->pad($row[$column], $width);
            }
        }

        return rtrim(implode(' ', $columns));
    }

    private function truncate(string $value, int $maximum): string
    {
        if (HordeString::length($value) <= $maximum) {
            return $value;
        }

        return HordeString::substr($value, 0, $maximum - 1) . '…';
    }

    private function pad(string $value, int $width): string
    {
        return $value . str_repeat(' ', max(0, $width - HordeString::length($value)));
    }
}
