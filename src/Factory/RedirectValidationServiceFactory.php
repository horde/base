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

use Horde\Horde\Service\RedirectValidationService;
use Horde\Injector\Injector;

/**
 * Factory for RedirectValidationService.
 */
class RedirectValidationServiceFactory
{
    public function create(Injector $injector): RedirectValidationService
    {
        return new RedirectValidationService(
            $injector->getInstance('Horde_Registry'),
            $GLOBALS['conf'] ?? [],
        );
    }
}
