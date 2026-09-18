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

use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Injector\Injector;
use Horde_Cli;
use Horde_Exception;
use Horde_Injector;
use InvalidArgumentException;

final class CommandRunner
{
    public function __construct(
        private readonly Horde_Cli $cli,
        private readonly Horde_Injector|Injector $injector
    ) {
    }

    public function run(array $argv): int
    {
        $command = array_shift($argv);
        if ($command === null) {
            $this->cli->writeln(Usage::text());
            return ExitCode::USAGE;
        }
        if (in_array($command, ['help', '--help', '-h'], true)) {
            $this->cli->writeln(Usage::text());
            return ExitCode::OK;
        }

        if (!in_array($command, ['summary', 'show', 'top'], true)) {
            $this->cli->message('Unknown command: ' . $command, 'cli.error');
            $this->cli->writeln(Usage::text());
            return ExitCode::USAGE;
        }

        try {
            $service = $this->injector->get(SnapshotService::class);

            return match ($command) {
                'summary' => (new SummaryCommand($this->cli, $service))->run($argv),
                'show' => (new ShowCommand($this->cli, $service))->run($argv),
                'top' => (new TopCommand($this->cli, $service))->run($argv),
            };
        } catch (InvalidArgumentException $e) {
            $this->cli->message($e->getMessage(), 'cli.error');
            $this->cli->writeln(Usage::text());
            return ExitCode::USAGE;
        } catch (Horde_Exception $e) {
            $this->cli->message(
                $this->cli->red('ActiveSync is unavailable: ' . $e->getMessage()),
                'cli.error'
            );
            return ExitCode::UNAVAILABLE;
        }
    }
}
