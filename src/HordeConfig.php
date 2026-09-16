<?php

declare(strict_types=1);
/**
 * Horde configuration class
 *
 * Provides access to the Horde configuration settings.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail']; *
 * New pattern: $config = $injector->get(HordeConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Horde;

use Horde\Core\Config\State;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: HordeConfigFactory::class, method: 'create')]
class HordeConfig extends State {}
