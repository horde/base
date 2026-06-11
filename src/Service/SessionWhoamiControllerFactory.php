<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Horde
 */

namespace Horde\Horde\Service;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

/**
 * DI factory for {@see SessionWhoamiController}.
 *
 * Only `ConfigLoader` is wired up via DI. The controller constructs an
 * `AuthCredentialStore` per-request bound to the session the modern
 * middleware loaded onto the request, NOT the DI-resolved one (which
 * is keyed to whatever `HordeSessionFactory` produced at request start
 * and is unrelated to the cookie path).
 */
class SessionWhoamiControllerFactory
{
    public function create(Injector $injector): SessionWhoamiController
    {
        return new SessionWhoamiController(
            $injector->getInstance(ConfigLoader::class),
        );
    }
}
