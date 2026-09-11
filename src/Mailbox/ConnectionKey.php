<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Composite identity for a live mailbox connection: the owning user plus the
 * account.
 *
 * MailboxAccount::getId() is user-scoped in meaning but user-agnostic in value
 * (the convention is '{providerId}:{hash(host+username)}'), so the same backend
 * yields the same account ID for every user. That is deliberate — per-account
 * config, UI state, and provider dedup all key on a stable account ID. It also
 * means the account ID alone does NOT identify a connection: in any context
 * that serves more than one user (a daemon, a Swoole worker, a queue consumer)
 * two users' connections to the same backend share an account ID but are
 * distinct streams with distinct credentials.
 *
 * ConnectionKey carries both dimensions so connection tables, health maps, and
 * caches can key on identity without smearing the user into the account ID.
 * In request-scoped code the userId is simply the single authenticated user.
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
final readonly class ConnectionKey
{
    /**
     * @param string $userId    Horde user ID of the account owner.
     * @param string $accountId Stable account ID from MailboxAccount::getId().
     */
    public function __construct(
        public string $userId,
        public string $accountId,
    ) {}

    /**
     * Build a key for the given account and owner.
     */
    public static function of(string $userId, MailboxAccount $account): self
    {
        return new self($userId, $account->getId());
    }

    /**
     * Value equality: same user and same account.
     */
    public function equals(ConnectionKey $other): bool
    {
        return $this->userId === $other->userId
            && $this->accountId === $other->accountId;
    }

    /**
     * Collision-safe string form for use as an array key.
     *
     * The NUL separator cannot occur in a Horde user ID or an account ID, so
     * distinct (userId, accountId) pairs never collapse to the same string
     * (unlike a bare concatenation, where "ab" + "c" and "a" + "bc" would).
     */
    public function __toString(): string
    {
        return $this->userId . "\0" . $this->accountId;
    }
}
