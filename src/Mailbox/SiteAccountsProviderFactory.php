<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Config\BackendConfigLoader;
use Horde\Injector\Injector;

/**
 * Produces SiteAccountsProvider instances for the DI container and for
 * ad-hoc wiring.
 *
 * ## Default DI entrypoint
 *
 * Register in IMP_Application::_bootstrap():
 *
 *   $injector->bindFactory(
 *       SiteAccountsProvider::class,
 *       SiteAccountsProviderFactory::class,
 *       'create'
 *   );
 *
 * create() produces a provider that picks up all non-disabled backends.
 *
 * ## Builder entrypoint
 *
 * Use fromScratch() when specific disabled backends must be included:
 *
 *   $provider = SiteAccountsProviderFactory::fromScratch($injector)
 *       ->withBackendKey('staging_imap')
 *       ->build();
 *
 * withBackendKey() may be chained any number of times. Each call returns
 * a new immutable instance. Duplicate keys are harmless.
 *
 * Both paths delegate to the same assemble() step, so the resulting
 * SiteAccountsProvider is identical in type regardless of which path
 * produced it.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
final class SiteAccountsProviderFactory
{
    /**
     * @param Injector|null $injector         Stored by the builder path; null when instantiated
     *                                        by DI for the create() entrypoint.
     * @param string[]      $forcedBackendKeys Keys registered via withBackendKey(). Resolved
     *                                        into the provider at build() or assemble() time.
     */
    public function __construct(
        private readonly ?Injector $injector = null,
        private readonly array $forcedBackendKeys = [],
    ) {}

    // -------------------------------------------------------------------------
    // Default DI entrypoint
    // -------------------------------------------------------------------------

    /**
     * Produces a SiteAccountsProvider covering all non-disabled backends.
     *
     * This is the method registered with bindFactory() in _bootstrap().
     */
    public function create(Injector $injector): SiteAccountsProvider
    {
        return $this->assemble($injector, []);
    }

    // -------------------------------------------------------------------------
    // Builder entrypoint and fluent methods
    // -------------------------------------------------------------------------

    /**
     * Start a builder that can force disabled backends into the provider.
     *
     * @return self Immutable builder with no forced backend keys.
     */
    public static function fromScratch(Injector $injector): self
    {
        return new self(injector: $injector);
    }

    /**
     * Force a backend into the provider's resolveAccounts() output, even if
     * backends.php marks it disabled.
     *
     * Intended for diagnostic scripts and controlled test setups.
     *
     * @return self New builder with the key appended.
     */
    public function withBackendKey(string $key): self
    {
        return new self(
            injector:          $this->injector,
            forcedBackendKeys: [...$this->forcedBackendKeys, $key],
        );
    }

    /**
     * Assemble and return a SiteAccountsProvider from the accumulated state.
     *
     * @throws \LogicException           When called on a DI-created instance that has
     *                                   no stored Injector. Use fromScratch($injector)
     *                                   to start the builder.
     * @throws \InvalidArgumentException When a forced backend key is not present
     *                                   in backends.php.
     */
    public function build(): SiteAccountsProvider
    {
        if ($this->injector === null) {
            throw new \LogicException(
                'build() requires an Injector. '
                    . 'Start the builder with SiteAccountsProviderFactory::fromScratch($injector).'
            );
        }

        return $this->assemble($this->injector, $this->forcedBackendKeys);
    }

    // -------------------------------------------------------------------------
    // Shared assembly: used by both create() and build()
    // -------------------------------------------------------------------------

    /**
     * @param string[] $forcedBackendKeys
     * @throws \InvalidArgumentException When a forced key is absent from backends.php.
     */
    private function assemble(Injector $injector, array $forcedBackendKeys): SiteAccountsProvider
    {
        $backendState = $injector->get(BackendConfigLoader::class)->load('imp', 'backends.php', 'servers');

        foreach ($forcedBackendKeys as $key) {
            if ($backendState->getBackend($key) === null) {
                throw new \InvalidArgumentException(sprintf(
                    'Backend key "%s" does not exist in backends.php.',
                    $key,
                ));
            }
        }

        return new SiteAccountsProvider(
            backendState:      $backendState,
            forcedBackendKeys: $forcedBackendKeys,
        );
    }
}
