<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Config\BackendState;

/**
 * Resolves MailboxAccounts from the site-wide backends.php configuration.
 *
 * This is the primary discovery provider: it translates every enabled
 * entry in backends.php into a SiteMailboxAccount. The manager then
 * hands each account to a connector to open a live connection; credential
 * resolution is a separate concern handled at connect time.
 *
 * ## Normal path (create() via DI)
 *
 * All backends whose 'disabled' flag is absent or false are included.
 * This mirrors the legacy behaviour of IMP_Imap::loadServerConfig().
 *
 * ## Override path (fromScratch() builder)
 *
 * withBackendKey(string $key) forces a specific backend into
 * resolveAccounts() even if backends.php marks it disabled. This is
 * intended for diagnostic scripts and controlled test setups, not for
 * production use.
 *
 * ## Introspection
 *
 * list(BackendFilter $filter) provides a pure view of the backend config
 * without the forced-key override. It is unaffected by withBackendKey().
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
final class SiteAccountsProvider implements MailboxProvider
{
    public const PROVIDER_ID = 'site_backends';

    /**
     * @param BackendState $backendState    Site-wide backend definitions from BackendConfigLoader.
     * @param string[]     $forcedBackendKeys Backend keys included regardless of disabled flag.
     */
    public function __construct(
        private readonly BackendState $backendState,
        private readonly array $forcedBackendKeys = [],
    ) {}

    // -------------------------------------------------------------------------
    // MailboxProvider interface
    // -------------------------------------------------------------------------

    public function getId(): string
    {
        return self::PROVIDER_ID;
    }

    /** @return string[] */
    public function getSupportedTypes(): array
    {
        return ['imap', 'pop3'];
    }

    /**
     * Priority 10: resolves first in the default manager ordering.
     * Replaces the legacy concept of a single 'primary' mailbox.
     */
    public function getPriority(): int
    {
        return 10;
    }

    /**
     * Returns SiteMailboxAccount instances for all enabled backends,
     * plus any backends whose key was explicitly registered via
     * SiteAccountsProviderFactory::withBackendKey().
     *
     * @return list<SiteMailboxAccount>
     */
    public function resolveAccounts(): array
    {
        $accounts = [];

        foreach ($this->backendState->listBackends(includeDisabled: true) as $key => $config) {
            $disabled = !empty($config['disabled']);
            $forced   = in_array($key, $this->forcedBackendKeys, true);

            if ($disabled && !$forced) {
                continue;
            }

            $accounts[] = $this->buildAccount($key, $config);
        }

        return $accounts;
    }

    // -------------------------------------------------------------------------
    // Introspection
    // -------------------------------------------------------------------------

    /**
     * List all backends matching the given filter as SiteMailboxAccount instances.
     *
     * Unlike resolveAccounts(), this method is a pure view of the backends.php
     * state: forced backend keys have no effect, and no session is consulted.
     * Use BackendFilter::Disabled to enumerate what has been turned off;
     * use BackendFilter::All for a complete inventory.
     *
     * @return list<SiteMailboxAccount>
     */
    public function list(BackendFilter $filter): array
    {
        $accounts = [];

        foreach ($this->backendState->listBackends(includeDisabled: true) as $key => $config) {
            $disabled = !empty($config['disabled']);

            $include = match ($filter) {
                BackendFilter::Enabled  => !$disabled,
                BackendFilter::Disabled => $disabled,
                BackendFilter::All      => true,
            };

            if ($include) {
                $accounts[] = $this->buildAccount($key, $config);
            }
        }

        return $accounts;
    }

    // -------------------------------------------------------------------------
    // Internals
    // -------------------------------------------------------------------------

    /**
     * Build a SiteMailboxAccount from a raw backend config array.
     *
     * Capabilities are inferred from the protocol:
     *   imap: FolderManagement, EfficientSearch, MessageFlags, MessageUpload
     *   pop3: (none — POP3 exposes no persistent folder tree or flag storage)
     */
    private function buildAccount(string $key, array $config): SiteMailboxAccount
    {
        $protocol = $config['protocol'] ?? 'imap';

        $capabilities = match ($protocol) {
            'pop3'  => [],
            default => [
                MailboxCapability::FolderManagement,
                MailboxCapability::EfficientSearch,
                MailboxCapability::MessageFlags,
                MailboxCapability::MessageUpload,
            ],
        };

        return new SiteMailboxAccount(
            id:           self::PROVIDER_ID . ':' . $key,
            label:        $config['name'] ?? $key,
            backendKey:   $key,
            protocol:     $protocol,
            capabilities: $capabilities,
            hostspec:     $config['hostspec'] ?? 'localhost',
            port:         isset($config['port']) ? (int) $config['port'] : null,
            secure:       $config['secure'] ?? 'tls',
        );
    }
}
