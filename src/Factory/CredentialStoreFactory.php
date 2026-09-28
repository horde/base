<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\NullCredentialStore;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlCredentialStore;
use Horde\Injector\Injector;
use Horde\Secret\SecretManager;

class CredentialStoreFactory
{
    public function create(Injector $injector): CredentialStore
    {
        $config = $injector->getInstance('Horde_Registry')->config();
        $driver = $config['password_credentials']['storage_driver'] ?? 'null';

        return match ($driver) {
            'sql' => new SqlCredentialStore(
                db: $injector->getInstance(Adapter::class),
                secret: $injector->getInstance(SecretManager::class),
            ),
            default => new NullCredentialStore(),
        };
    }
}
