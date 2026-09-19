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

use Horde\Argv\Exception as ArgvException;
use Horde\Cli\Cli;
use Horde\Core\ActiveSync\Ops\SnapshotService;
use Horde\Exception\HordeThrowable;
use Horde\Injector\Injector;
use InvalidArgumentException;

final class CommandRunner
{
    public function __construct(
        private readonly Cli $cli,
        private readonly Injector $injector
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

        if (!in_array($command, ['summary', 'show'], true)) {
            $this->cli->message('Unknown command: ' . $command, 'cli.error');
            $this->cli->writeln(Usage::text());
            return ExitCode::USAGE;
        }

        try {
            $service = $this->injector->get(SnapshotService::class);

            return match ($command) {
                'summary' => (new SummaryCommand($this->cli, $service))->run($argv),
                'show' => (new ShowCommand($this->cli, $service))->run($argv),
            };
        } catch (InvalidArgumentException|ArgvException $e) {
            $this->cli->message($e->getMessage(), 'cli.error');
            $this->cli->writeln(Usage::text());
            return ExitCode::USAGE;
        } catch (HordeThrowable $e) {
            $this->cli->message(
                $this->cli->red('ActiveSync is unavailable: ' . $e->getMessage()),
                'cli.error'
            );
            return ExitCode::UNAVAILABLE;
        }
    }
}
