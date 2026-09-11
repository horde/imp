<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Resolves MailboxAccount instances for the current user.
 *
 * Providers are the pluggable extension point of the account model. Each
 * provider covers a distinct credential or discovery mechanism. The manager
 * deduplicates accounts by ID across providers: the first provider (lowest
 * priority integer) to emit an account with a given ID wins.
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
interface MailboxProvider
{
    /**
     * Unique, stable identifier for this provider.
     *
     * Convention: lowercase snake_case, e.g. 'session_credential',
     * 'user_prefs', 'oidc', 'horde_registry', 'jonah_feeds'.
     */
    public function getId(): string;

    /**
     * Account type tokens this provider can emit.
     *
     * Well-known values: 'imap', 'pop3', 'rss', 'atom', 'jonah'.
     *
     * @return string[]
     */
    public function getSupportedTypes(): array;

    /**
     * Resolution priority. Lower integer = higher priority = resolved first.
     *
     * Convention:
     *   10  session_credential  (replaces legacy 'primary' mailbox concept)
     *   20  user_prefs          (replaces IMP_Remote)
     *   30  oidc
     *   40  horde_registry
     *   50  feed providers
     *
     * Leave gaps for future insertion between existing providers.
     */
    public function getPriority(): int;

    /**
     * Resolves and returns the accounts this provider makes available.
     *
     * The provider is constructed by the DI container with whatever
     * user-scoped services it requires (prefs, session reader, token store,
     * etc.). No runtime context is passed at call time.
     *
     * Implementations MUST return an empty array rather than throwing when
     * no accounts are available (e.g. an OIDC provider whose token carries
     * no mailbox claims). Exceptions are reserved for unrecoverable
     * configuration errors.
     *
     * @return MailboxAccount[]
     */
    public function resolveAccounts(): array;
}
