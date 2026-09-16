<?php

declare(strict_types=1);
/**
 * Horde configuration class factory
 *
 * Creates instances of the HordeConfig class.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(HordeConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Horde;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

class HordeConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): HordeConfig
    {
        $state = $this->injector->get(ConfigLoader::class)->load('horde');
        return new HordeConfig($state->toArray());
    }
}
