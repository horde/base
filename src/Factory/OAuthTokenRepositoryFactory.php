<?php

declare(strict_types=1);

/**
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category Horde
 * @package  Horde
 * @author   Ralf Lang <ralf.lang@ralf-lang.de>
 * @license  http://www.horde.org/licenses/lgpl21 LGPL 2.1
 */

namespace Horde\Horde\Factory;

use Horde\Core\Service\OAuthTokenRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlOAuthTokenRepository;
use Horde\Injector\Injector;
use Horde\Secret\SecretManager;

/**
 * Factory for OAuthTokenRepository.
 *
 * Returns SqlOAuthTokenRepository backed by the Horde DB adapter.
 */
class OAuthTokenRepositoryFactory
{
    public function create(Injector $injector): OAuthTokenRepository
    {
        return new SqlOAuthTokenRepository(
            db: $injector->getInstance(Adapter::class),
            secret: $injector->getInstance(SecretManager::class),
        );
    }
}
