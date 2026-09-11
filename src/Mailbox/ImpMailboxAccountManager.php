<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Concrete MailboxAccountManager for IMP's federated mailbox model.
 *
 * Constructed and registered by MailboxAccountManagerFactory when
 * app_auth_mode = 'federated'. Not intended for direct instantiation.
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
final class ImpMailboxAccountManager implements MailboxAccountManager
{
    /**
     * Providers sorted ascending by priority at construction time.
     *
     * @var MailboxProvider[]
     */
    private readonly array $providers;

    /**
     * @param MailboxProvider[] $providers Sorted by getPriority() ascending.
     */
    public function __construct(
        array $providers,
        private readonly ConnectionCache $cache = new NullConnectionCache(),
    ) {
        usort(
            $providers,
            static fn(MailboxProvider $a, MailboxProvider $b)
                => $a->getPriority() <=> $b->getPriority()
        );
        $this->providers = $providers;
    }

    public function getAccounts(): array
    {
        $seen     = [];
        $accounts = [];

        foreach ($this->providers as $provider) {
            foreach ($provider->resolveAccounts() as $account) {
                $id = $account->getId();
                if (!array_key_exists($id, $seen)) {
                    $seen[$id]  = true;
                    $accounts[] = $account;
                }
            }
        }

        return $accounts;
    }

    public function getAccount(string $id): MailboxAccount
    {
        foreach ($this->providers as $provider) {
            foreach ($provider->resolveAccounts() as $account) {
                if ($account->getId() === $id) {
                    return $account;
                }
            }
        }

        throw new MailboxAccountNotFound($id);
    }

    public function getConnection(MailboxAccount $account): MailboxConnection
    {
        // @todo Resolve the current user's ID and a MailboxCredential for this
        //       account (from the session AuthCredentialStore or the
        //       CredentialVault), then delegate to getConnectionWith():
        //
        //   $userId     = $this->session->getAuthId();
        //   $credential = $this->credentialStore->find($account->getId());
        //   return $this->getConnectionWith($account, $credential, $userId);
        //
        // Until a credential source exists, callers that already hold a
        // credential (the CLI diagnostic, a daemon that unrolled it from a
        // vault) call getConnectionWith() directly.
        throw new \LogicException(
            'getConnection() requires a credential source (AuthCredentialStore / '
                . 'CredentialVault) — not yet implemented. Use getConnectionWith() '
                . 'when you already hold a credential.'
        );
    }

    public function getConnectionWith(
        MailboxAccount $account,
        MailboxCredential $credential,
        string $userId,
    ): MailboxConnection {
        if ($credential->getAccountId() !== $account->getId()) {
            throw new \InvalidArgumentException(sprintf(
                'Credential is for account "%s", not "%s".',
                $credential->getAccountId(),
                $account->getId(),
            ));
        }

        // Cache by the composite (userId, accountId): the same backend yields
        // the same account ID for every user, so the account ID alone would
        // collide across users in a multi-user (daemon/worker) context.
        $key = (string) ConnectionKey::of($userId, $account);

        $cached = $this->cache->get($key);
        if ($cached !== null) {
            return $cached;
        }

        $connection = match ($account->getProtocol()) {
            'imap'  => new ImpImapConnection($account, $credential, $userId),
            // @todo 'pop3' => new ImpPop3Connection($account, $credential, $userId),
            default => throw new \LogicException(sprintf(
                'No connection type for protocol "%s" (account "%s").',
                $account->getProtocol(),
                $account->getId(),
            )),
        };

        $this->cache->set($key, $connection);

        return $connection;
    }
}
