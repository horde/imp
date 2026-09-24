<?php

declare(strict_types=1);
/**
 * IMP configuration class
 *
 * Provides access to the IMP configuration settings.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail'];
 * New pattern: $config = $injector->get(ImpConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Imp;

use Horde\Core\Config\State;
use Horde\Injector\Attribute\Factory;

#[Factory(factory: ImpConfigFactory::class, method: 'create')]
class ImpConfig extends State {}
