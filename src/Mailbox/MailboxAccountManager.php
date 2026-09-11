<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Primary entry point for mailbox account resolution and connection management.
 *
 * Application code and other Horde apps consuming the IMP mailbox API
 * depend only on this interface.
 *
 * Accounts are resolved from registered MailboxProvider instances and
 * deduplicated by ID. Connections are lazy: no protocol stream is opened
 * until a data-access method is called on the returned MailboxConnection.
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
interface MailboxAccountManager
{
    /**
     * Returns all accounts from all registered providers, ordered by
     * provider priority (lowest integer first).
     *
     * Providers are constructed by the DI container with whatever user-scoped
     * services they require; no runtime argument is needed here.
     * The returned array is keyed numerically; callers MUST NOT rely on array
     * keys for identity — use MailboxAccount::getId() instead.
     *
     * @return MailboxAccount[]
     */
    public function getAccounts(): array;

    /**
     * Returns the account with the given ID.
     *
     * Providers are queried in priority order; the first match is returned.
     * The full provider set is not necessarily consulted once a match is found.
     *
     * @throws MailboxAccountNotFound if no provider resolves the account
     */
    public function getAccount(string $id): MailboxAccount;

    /**
     * Returns a connection handle for the given account, resolving the
     * credential and owning user from the ambient request context (session
     * credential store / vault).
     *
     * No protocol stream is opened until a data-access method is called on
     * the returned object.
     *
     * Callers outside a request lifecycle (daemons, workers, CLI) that already
     * hold a credential should use getConnectionWith() instead.
     *
     * @throws MailboxAccountNotFound if no provider resolves the account
     * @throws \RuntimeException if no credential is available for this account
     */
    public function getConnection(MailboxAccount $account): MailboxConnection;

    /**
     * Returns a connection handle for the given account using an explicitly
     * supplied credential and owner.
     *
     * This is the operation for contexts with no ambient session: daemons,
     * Swoole workers, queue consumers, and CLI diagnostics that unrolled the
     * credential themselves. Connections are cached per (userId, accountId),
     * so one process may hold distinct connections to the same backend for
     * different users without collision.
     *
     * No protocol stream is opened until a data-access method is called on
     * the returned object.
     *
     * @param string $userId Horde user ID of the account owner.
     *
     * @throws \InvalidArgumentException if the credential's account ID does not
     *                                   match the account.
     */
    public function getConnectionWith(
        MailboxAccount $account,
        MailboxCredential $credential,
        string $userId,
    ): MailboxConnection;
}
