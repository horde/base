<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Injector\Injector;
use Psr\Log\LoggerInterface;

/**
 * Factory for PSR-3 Logger
 *
 * Creates a PSR-3 compliant logger that wraps Horde_Log.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */
class LoggerFactory
{
    /**
     * Create PSR-3 logger instance
     *
     * @param Injector $injector
     * @return LoggerInterface
     */
    public function create(Injector $injector): LoggerInterface
    {
        // Get Horde_Log_Logger instance (configured via registry)
        $hordeLogger = $injector->getInstance('Horde_Log_Logger');

        // Wrap it in PSR-3 adapter
        return new \Horde\Log\Psr3Adapter($hordeLogger);
    }
}
