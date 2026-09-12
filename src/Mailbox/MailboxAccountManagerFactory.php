<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Injector\Injector;

/**
 * Assembles ImpMailboxAccountManager from injector-resolved providers and
 * connectors.
 *
 * ## Default DI entrypoint
 *
 * Registered in IMP_Application::_bootstrap() when app_auth_mode = 'federated':
 *
 *   $injector->bindFactory(
 *       MailboxAccountManager::class,
 *       MailboxAccountManagerFactory::class,
 *       'create'
 *   );
 *
 * Resolves providers from the PROVIDERS class-name list. Each provider must be
 * registered with its own factory in _bootstrap() before MailboxAccountManager
 * is first resolved.
 *
 * ## Builder entrypoint (ad-hoc wiring)
 *
 * For diagnostic scripts, tests, or any context that needs to supply
 * providers directly without going through the DI-configured list:
 *
 *   $manager = MailboxAccountManagerFactory::fromScratch($injector)
 *       ->withProvider($adHocProvider)           // pre-constructed instance
 *       ->withProviderClass(MyProvider::class)   // resolved via injector at build()
 *       ->build();
 *
 * Both paths share the same assemble() step, keeping construction logic in
 * one place.
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
final class MailboxAccountManagerFactory
{
    // -------------------------------------------------------------------------
    // DI configuration: used by create() only
    // -------------------------------------------------------------------------

    /**
     * Ordered list of MailboxProvider classes to resolve from the injector.
     * Priority is determined by each provider's getPriority() return value;
     * declaration order here is a tiebreaker only.
     *
     * @var list<class-string<MailboxProvider>>
     */
    private const PROVIDERS = [
        SiteAccountsProvider::class,
        // @todo SessionCredentialProvider::class,
        // @todo UserPrefsMailboxProvider::class,
        // @todo OidcClaimsMailboxProvider::class,
    ];

    // -------------------------------------------------------------------------
    // Builder state: populated by fromScratch() and with*() methods
    // -------------------------------------------------------------------------

    /**
     * @param Injector|null         $injector        Stored by the builder path; null when
     *                                               instantiated by DI for create().
     * @param list<MailboxProvider> $providers       Pre-constructed provider instances.
     * @param list<string>          $providerClasses Provider class names. Resolved via injector at build() time.
     */
    public function __construct(
        private readonly ?Injector $injector = null,
        private readonly array $providers = [],
        private readonly array $providerClasses = [],
    ) {}

    // -------------------------------------------------------------------------
    // Default DI entrypoint
    // -------------------------------------------------------------------------

    /**
     * Assembles ImpMailboxAccountManager from the PROVIDERS class-name list,
     * resolving each via the injector.
     *
     * This is the method registered with bindFactory() in _bootstrap().
     */
    public function create(Injector $injector): MailboxAccountManager
    {
        return $this->assemble(
            providers: array_map(
                static fn(string $class) => $injector->get($class),
                self::PROVIDERS
            ),
            credentialSource: $injector->get(MailboxCredentialSource::class),
        );
    }

    // -------------------------------------------------------------------------
    // Builder entrypoint + fluent methods
    // -------------------------------------------------------------------------

    /**
     * Start a builder for an ad-hoc MailboxAccountManager.
     *
     * @return self Immutable builder with no providers.
     */
    public static function fromScratch(Injector $injector): self
    {
        return new self(injector: $injector);
    }

    /**
     * Add a pre-constructed MailboxProvider instance.
     *
     * @return self New builder with the provider appended.
     */
    public function withProvider(MailboxProvider $provider): self
    {
        return new self(
            injector:        $this->injector,
            providers:       [...$this->providers, $provider],
            providerClasses: $this->providerClasses,
        );
    }

    /**
     * Add a MailboxProvider by class name, resolved via the injector at build() time.
     *
     * @param class-string<MailboxProvider> $class
     * @return self New builder with the class appended.
     */
    public function withProviderClass(string $class): self
    {
        return new self(
            injector:        $this->injector,
            providers:       $this->providers,
            providerClasses: [...$this->providerClasses, $class],
        );
    }

    /**
     * Assemble and return an ImpMailboxAccountManager from the ad-hoc list
     * accumulated by the builder.
     *
     * @throws \LogicException When no Injector is stored (i.e. called on a
     *                         DI-created instance rather than a fromScratch() chain).
     */
    public function build(): MailboxAccountManager
    {
        if ($this->injector === null) {
            throw new \LogicException(
                'build() requires an Injector. Start the builder with '
                    . 'MailboxAccountManagerFactory::fromScratch($injector).'
            );
        }

        return $this->assemble(
            providers: [
                ...$this->providers,
                ...array_map(
                    fn(string $class) => $this->injector->get($class),
                    $this->providerClasses
                ),
            ],
        );
    }

    // -------------------------------------------------------------------------
    // Shared assembly — used by both create() and build()
    // -------------------------------------------------------------------------

    /**
     * Construct the ImpMailboxAccountManager from the resolved provider array.
     * Both entrypoints delegate here.
     *
     * @param list<MailboxProvider>        $providers
     * @param MailboxCredentialSource|null $credentialSource Credential source for
     *                                                       getConnection(). Null on the
     *                                                       builder/CLI path, which uses
     *                                                       getConnectionWith() explicitly.
     */
    private function assemble(
        array $providers,
        ?MailboxCredentialSource $credentialSource = null,
    ): ImpMailboxAccountManager {
        return new ImpMailboxAccountManager(
            providers: $providers,
            credentialSource: $credentialSource,
        );
    }
}
