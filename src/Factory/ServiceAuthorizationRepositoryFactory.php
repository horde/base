<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Core\Service\ServiceAuthorizationRepository;
use Horde\Core\Service\TokenGrantRepository;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlServiceAuthorizationRepository;
use Horde\Injector\Injector;

class ServiceAuthorizationRepositoryFactory
{
    public function create(Injector $injector): ServiceAuthorizationRepository
    {
        return new SqlServiceAuthorizationRepository(
            db: $injector->getInstance(Adapter::class),
            grantRepo: $injector->getInstance(TokenGrantRepository::class),
        );
    }
}
