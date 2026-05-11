<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package  Horde
 */

namespace Horde\Horde\Factory;

use Horde\Horde\Service\AuditService;
use Horde\Injector\Injector;
use Psr\Log\LoggerInterface;

/**
 * Factory for AuditService.
 */
class AuditServiceFactory
{
    public function create(Injector $injector): AuditService
    {
        return new AuditService(
            $injector->getInstance(LoggerInterface::class),
        );
    }
}
