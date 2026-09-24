<?php

declare(strict_types=1);
/**
 * IMP configuration class factory
 *
 * Creates instances of the ImpConfig class.
 *
 * Old pattern: globals $conf; $somethingDetail = $conf['something']['detail'];
 * New pattern: $config = $injector->get(ImpConfig::class); $somethingDetail = $config->get('something.detail');
 *
 * Prefer DI over instantiating $config in your code.
 */

namespace Horde\Imp;

use Horde\Core\Config\ConfigLoader;
use Horde\Injector\Injector;

class ImpConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): ImpConfig
    {
        $state = $this->injector->get(ConfigLoader::class)->load('imp');
        return new ImpConfig($state->toArray());
    }
}
