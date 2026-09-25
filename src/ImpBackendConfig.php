<?php

declare(strict_types=1);

namespace Horde\Imp;

use Horde\Core\Config\BackendState;
use Horde\Injector\Attribute\Factory;

/**
 * IMP backend configuration class
 *
 * Provides access to mail server backend definitions from backends.php.
 * IMP uses 'servers' instead of 'backends' as the variable name.
 */
#[Factory(factory: ImpBackendConfigFactory::class, method: 'create')]
class ImpBackendConfig extends BackendState {}
