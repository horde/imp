<?php

declare(strict_types=1);

namespace Horde\Imp;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Injector\Injector;

/**
 * Factory for ImpBackendConfig
 *
 * Loads backend configuration from backends.php using BackendConfigLoader.
 * IMP uses 'servers' as the variable name instead of 'backends'.
 */
class ImpBackendConfigFactory
{
    public function __construct(private Injector $injector) {}

    public function create(): ImpBackendConfig
    {
        $loader = $this->injector->get(BackendConfigLoader::class);

        // IMP uses 'servers' variable in backends.php
        $state = $loader->load('imp', 'backends.php', 'servers');

        return new ImpBackendConfig($state->toArray());
    }
}
