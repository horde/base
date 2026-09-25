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
use Horde\Argv\Modern\Builder\OptionBuilder;
use Horde\Argv\Modern\Builder\ParserBuilder;
use Horde\Argv\Modern\Enum\OptionType;
use Horde\Argv\Modern\Exception\AmbiguousOptionException;
use Horde\Argv\Modern\Exception\ConflictingOptionException;
use Horde\Argv\Modern\Exception\InvalidArgumentCountException;
use Horde\Argv\Modern\Exception\InvalidOptionException;
use Horde\Argv\Modern\Exception\MissingValueException;
use Horde\Argv\Modern\Exception\ValueValidationException;
use Horde\Argv\Modern\Result\ParseResult;
use Horde\Cli\Cli;
use Horde\Core\ActiveSync\Ops\FleetSnapshot;
use Horde\Core\ActiveSync\Ops\SnapshotCriteria;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Exception\HordeThrowable;
use Horde\Util\HordeString;
use InvalidArgumentException;

final class TopCommand
{
    private readonly Closure $sleep;
    private readonly Closure $clock;

    public function __construct(
        private readonly Cli $cli,
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
        $result = $this->parse($argv);
        $values = $result->options;
        if ($result->arguments !== []) {
            throw new InvalidArgumentException('Top does not accept positional arguments.');
        }
        if (!in_array($values->get('format'), ['table', 'json'], true)) {
            throw new InvalidArgumentException('Format must be table or json.');
        }
        if ($values->get('interval') < 0) {
            throw new InvalidArgumentException('Watch interval cannot be negative.');
        }
        if ($values->get('iterations') < 0) {
            throw new InvalidArgumentException('Iterations cannot be negative.');
        }

        $criteria = new SnapshotCriteria(
            user: $values->get('user'),
            deviceId: $values->get('device'),
            activeWithin: $values->get('activeWithin'),
            healthMin: $values->get('health'),
            stuckOnly: $values->get('stuckOnly'),
            limit: $values->get('limit'),
            sort: $values->get('sort')
        );

        $watch = $values->get('watch') && $values->get('format') !== 'json';
        $rendered = 0;
        do {
            if ($watch) {
                $this->clearForWatch();
            }

            try {
                $snapshot = $this->service->fleet($criteria);
            } catch (HordeThrowable $e) {
                $this->cli->message(
                    $this->cli->red('ActiveSync is unavailable: ' . $e->getMessage()),
                    'cli.error'
                );
                return ExitCode::UNAVAILABLE;
            }

            if ($values->get('format') === 'json') {
                $this->cli->writeln(Formatter::json($snapshot->toArray()));
            } else {
                $this->renderTable($snapshot);
            }

            ++$rendered;
            if (!$watch || ($values->get('iterations') > 0 && $rendered >= $values->get('iterations'))) {
                break;
            }
            ($this->sleep)($values->get('interval'));
        } while (true);

        return ExitCode::OK;
    }

    private function parse(array $argv): ParseResult
    {
        $stringOption = static fn (string $name, string $destination, mixed $default = null) => OptionBuilder::create()
            ->long($name)
            ->dest($destination)
            ->default($default)
            ->build();
        $integerOption = static fn (string $name, string $destination, int $default) => OptionBuilder::create()
            ->long($name)
            ->dest($destination)
            ->type(OptionType::Int)
            ->default($default)
            ->build();

        try {
            return ParserBuilder::create()
                ->addOptions([
                    OptionBuilder::create()->long('--watch')->dest('watch')->flag()->default(false)->build(),
                    $integerOption('--interval', 'interval', 2),
                    OptionBuilder::create()->long('--active-within')->dest('activeWithin')->type(OptionType::Int)->build(),
                    $stringOption('--user', 'user'),
                    $stringOption('--device', 'device'),
                    $stringOption('--health', 'health'),
                    OptionBuilder::create()->long('--stuck-only')->dest('stuckOnly')->flag()->default(false)->build(),
                    $stringOption('--sort', 'sort', SnapshotCriteria::SORT_HEALTH),
                    $stringOption('--format', 'format', 'table'),
                    $integerOption('--limit', 'limit', 50),
                    $integerOption('--iterations', 'iterations', 0),
                ])
                ->build()
                ->parse($argv);
        } catch (
            AmbiguousOptionException
            | ConflictingOptionException
            | InvalidArgumentCountException
            | InvalidOptionException
            | MissingValueException
            | ValueValidationException $e
        ) {
            throw new InvalidArgumentException($e->getMessage(), 0, $e);
        }
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
