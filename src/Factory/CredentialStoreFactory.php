<?php

declare(strict_types=1);

namespace Horde\Horde\Factory;

use Horde\Core\Service\CredentialStore;
use Horde\Core\Service\NullCredentialStore;
use Horde\Db\Adapter;
use Horde\Horde\Service\SqlCredentialStore;
use Horde\Injector\Injector;
use Horde\Secret\SecretManager;
use Horde\Horde\HordeConfig;

/**
 * Horde Base's default CredentialStore factory. Do not hint against it directly.
 *
 * This is intended to be hooked into the Horde\Core\Service\Factory\CredentialStoreFactory via a config key.
 *
 * Downstream integrators and developers of new upstream credential store implementations (i.e. bindings to some vault technology) can override this factory via the config key.
 *
 * This is supposed to keep the mechanism open for extension and free of circular dependencies.
 *
 * @author Ralf Lang <ralf.lang@ralf-lang.de>
 * @package Horde\Horde\Factory
 */
class CredentialStoreFactory
{
    public function create(Injector $injector): CredentialStore
    {
        $config = $injector->get(HordeConfig::class);
        $driver = $config->get('password_credentials.storage_driver'); //?? 'null';

        // TODO: Current implementation ignores custom storage driver settings within the 'password_credentials' config.
        return match ($driver) {
            'sql' => new SqlCredentialStore(
                db: $injector->get(Adapter::class),
                secret: $injector->get(SecretManager::class),
            ),
            default => new NullCredentialStore(),
        };
    }
}
