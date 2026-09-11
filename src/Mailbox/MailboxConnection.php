<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Represents a connection to a mailbox.
 *
 * All connections returned by MailboxAccountManager are lazy proxies:
 * the underlying protocol client is not instantiated until the first
 * data-access method is called (e.g. ImapConnection::getClient()).
 *
 * Callers MUST NOT assume the connection is live when they receive this
 * interface. Use isInitialized() to inspect proxy state without triggering
 * a connection attempt.
 *
 * Calling disconnect() on a lazy proxy that has never been initialised is
 * a no-op. Calling it on an open connection closes the protocol stream and
 * dispatches a ConnectionClosed event. The proxy may be re-used afterwards;
 * the next data-access call will re-open the connection.
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
interface MailboxConnection
{
    /**
     * The account this connection was created for.
     */
    public function getAccount(): MailboxAccount;

    /**
     * Closes the underlying protocol stream and releases its resources.
     *
     * Safe to call on an uninitialised proxy (no-op). After disconnecting,
     * the next call to a data-access method will re-open the connection.
     */
    public function disconnect(): void;
}
