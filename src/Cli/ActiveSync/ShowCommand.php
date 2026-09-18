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
use Horde_Argv_Parser;
use Horde_Cli;
use Horde_Exception_NotFound;
use InvalidArgumentException;

final class ShowCommand
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

        [$values, $args] = $parser->parseArgs($argv);
        if (count($args) !== 2) {
            throw new InvalidArgumentException('Show requires USER and DEVICE.');
        }
        if (!in_array($values->format, ['table', 'json'], true)) {
            throw new InvalidArgumentException('Format must be table or json.');
        }

        try {
            $device = $this->service->device($args[0], $args[1]);
        } catch (Horde_Exception_NotFound $e) {
            $this->cli->message($e->getMessage(), 'cli.error');
            return ExitCode::CRITICAL;
        }

        if ($values->format === 'json') {
            $this->cli->writeln(Formatter::json($device->toArray()));
            return ExitCode::OK;
        }

        $this->cli->writeln(sprintf(
            '%s %s (%s, EAS %s)',
            $device->user,
            $device->deviceId,
            $device->deviceType,
            $device->version ?? 'n/a'
        ));
        $this->cli->writeln('Status: ' . Formatter::colorStatus($this->cli, $device->status));
        $this->cli->writeln('Active: ' . ($device->active ? 'yes' : 'no'));
        $this->cli->writeln('Age: ' . Formatter::humanAge($device->ageSeconds));
        $this->cli->writeln('Heartbeat: ' . ($device->hbinterval === null ? 'n/a' : $device->hbinterval . 's'));
        $this->cli->writeln('FSR: ' . $device->foldersyncrequired);
        $this->cli->writeln('Log: ' . ($device->logPath ?? 'n/a'));
        $this->cli->writeln('Signals:');
        if ($device->signals === []) {
            $this->cli->writeln('- none');
        }
        foreach ($device->signals as $signal) {
            $this->cli->writeln(sprintf(
                '- %s [%s] %s',
                $signal->code,
                Formatter::colorStatus($this->cli, $signal->severity),
                $signal->detail
            ));
        }

        $this->cli->writeln('Collections:');
        if ($device->collections === []) {
            $this->cli->writeln('- none');
        }
        foreach ($device->collections as $collection) {
            $this->cli->writeln(sprintf(
                '- %s class=%s serverid=%s synckey=%s status=%s',
                $collection->id,
                $collection->class ?? 'n/a',
                $collection->serverid ?? 'n/a',
                $collection->lastsynckey ?? 'n/a',
                Formatter::colorStatus($this->cli, $collection->status)
            ));
            foreach ($collection->signals as $signal) {
                $this->cli->writeln(sprintf(
                    '    - %s [%s] %s',
                    $signal->code,
                    Formatter::colorStatus($this->cli, $signal->severity),
                    $signal->detail
                ));
            }
        }

        return ExitCode::OK;
    }
}
