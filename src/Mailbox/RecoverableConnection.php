<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * A MailboxConnection whose underlying stream can drop and be re-established
 * without discarding the connection object.
 *
 * Request-scoped code rarely needs this: a connection built during one HTTP
 * request is used and torn down within that request, long before an idle
 * server timeout matters. Long-running processes are different — a daemon,
 * Swoole worker, or queue consumer holds a connection across many units of
 * work, and the IMAP server will drop an idle stream (timeout, NAT, BYE) out
 * from under it. For those callers, "a client was built" (isInitialized) is
 * not the same as "the stream is usable right now".
 *
 * Callers that outlive a request check for this capability and heal the
 * connection between units of work:
 *
 *   if ($connection instanceof RecoverableConnection) {
 *       $client = $connection->ensureLive();   // reconnect + re-auth if needed
 *   }
 *
 * Reconnecting re-reads the MailboxCredential, so a transparently refreshed
 * OAuth token or a rotated password is picked up on reconnect. This is the
 * same mechanism that recovers from a dropped socket: both go through a clean
 * rebuild rather than mutating connection state in place.
 *
 * Non-network connections (feed/RSS accounts, test doubles with a fixed
 * client) do not implement this interface.
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
interface RecoverableConnection extends MailboxConnection
{
    /**
     * Returns true if the underlying stream is open AND authenticated right now.
     *
     * This is a liveness probe, not an initialization flag: it may perform a
     * lightweight server round-trip (e.g. NOOP). It MUST NOT silently open a
     * fresh unauthenticated stream as a side effect of answering — a probe that
     * reconnects-without-login would corrupt the authenticated invariant. An
     * idle, never-built, or logged-out connection returns false rather than
     * connecting.
     *
     * False does not mean the account is unreachable; it means the caller
     * should reconnect() (or ensureLive()) before issuing commands.
     */
    public function isAlive(): bool;

    /**
     * Tears down any existing stream and establishes a fresh authenticated one.
     *
     * Re-reads the MailboxCredential, so token refreshes and password rotations
     * take effect here. Safe to call whether or not a stream is currently open.
     *
     * @throws \Horde\Imap\Client\Exception\ConnectionException     if the stream cannot be opened.
     * @throws \Horde\Imap\Client\Exception\AuthenticationException if authentication fails.
     */
    public function reconnect(): void;
}
