<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Stores and retrieves open MailboxConnection instances within a defined scope.
 *
 *  RequestScopedConnectionCache   default in-memory, per HTTP request
 *  SessionScopedConnectionCache   persists across requests for daemons / CLI
 *  NullConnectionCache            no reuse for stateless / test contexts
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
interface ConnectionCache
{
    /**
     * Returns a cached connection for the given account ID, or null on miss.
     */
    public function get(string $accountId): ?MailboxConnection;

    /**
     * Stores a connection in the cache under the given account ID.
     */
    public function set(string $accountId, MailboxConnection $connection): void;

    /**
     * Removes a connection from the cache.
     *
     * Called after an authentication failure or explicit disconnect to prevent
     * a stale connection object from being returned on the next lookup.
     */
    public function invalidate(string $accountId): void;
}
