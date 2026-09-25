<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Core\Service\TokenGrantRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlTokenGrantRepository;
use Horde\Injector\Binder;
use Horde\Injector\Injector;
use Horde\Secret\SecretManager;

class TokenGrantRepositoryFactory
{
    public function create(Injector $injector): TokenGrantRepository
    {
        return new SqlTokenGrantRepository(
            db: $injector->getInstance(Adapter::class),
            secret: $injector->getInstance(SecretManager::class),
        );
    }
}
