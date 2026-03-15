<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL-2). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */

namespace Horde\Horde\Factory;

use Horde\Horde\Service\Health\HealthCheckService;
use Horde_Injector;

/**
 * Factory for HealthCheckService
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl LGPL-2
 */
class HealthCheckServiceFactory
{
    public function create(Horde_Injector $injector): HealthCheckService
    {
        return new HealthCheckService($injector);
    }
}
