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

use Horde\Core\ActiveSync\Ops\SnapshotCriteria;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde_Argv_Parser;
use Horde_Cli;
use InvalidArgumentException;

final class SummaryCommand
{
    public function __construct(
        private readonly Horde_Cli $cli,
        private readonly SnapshotService $service
    ) {
    }

    public function run(array $argv): int
    {
        $parser = new Horde_Argv_Parser(['addHelpOption' => false]);
        $parser->addOption('--format', [
            'dest' => 'format',
            'default' => 'table',
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
        $parser->addOption('--limit', [
            'dest' => 'limit',
            'type' => 'int',
            'default' => 0,
        ]);

        [$values, $args] = $parser->parseArgs($argv);
        if ($args !== []) {
            throw new InvalidArgumentException('Summary does not accept positional arguments.');
        }
        if (!in_array($values->format, ['table', 'json'], true)) {
            throw new InvalidArgumentException('Format must be table or json.');
        }

        $criteria = new SnapshotCriteria(
            user: $values->user,
            deviceId: $values->device,
            activeWithin: $values->activeWithin,
            healthMin: $values->health,
            stuckOnly: $values->stuckOnly,
            limit: $values->limit
        );
        $summary = $this->service->summary($criteria);

        if ($values->format === 'json') {
            $this->cli->writeln(Formatter::json($summary->toArray()));
        } else {
            foreach ([
                'Devices' => $summary->devices,
                'Active' => $summary->active,
                'OK' => $summary->ok,
                'Warn' => $summary->warn,
                'Critical' => $summary->critical,
                'Stuck' => $summary->stuck,
                'Wipe pending' => $summary->wipePending,
                'Blocked' => $summary->blocked,
                'As of' => date('c', $summary->asOf),
            ] as $label => $value) {
                $this->cli->writeln(sprintf('%-12s: %s', $label, $value));
            }
        }

        return $summary->hasCritical() ? ExitCode::CRITICAL : ExitCode::OK;
    }
}
