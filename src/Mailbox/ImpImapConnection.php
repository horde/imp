<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Auth\HasCredentialsState;
use Horde\Imap\Client\ConnectionConfig;
use Horde\Imap\Client\Exception\ConnectionException;
use Horde\Imap\Client\ImapClient;
use Horde\Imap\Client\SecureMode;
use Horde\Socket\Client\Exception\SocketException;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * A lazy, recoverable MailboxConnection backed by a Horde\Imap\Client\ImapClient.
 *
 * Construction opens nothing. The underlying ImapClient is built on the first
 * getClient() call from the account's connection parameters plus the supplied
 * credential; even then no socket is opened until the first protocol command
 * runs, and authentication happens only when the caller explicitly invokes
 * login() on the client. This realises the account model's laziness contract:
 * obtaining a connection never implies an open stream or a login.
 *
 * getClient() exposes the concrete ImapClient so callers can drive IMAP
 * operations directly, including the explicit login() step that ImapProtocol
 * does not surface.
 *
 * For long-running callers (daemons, Swoole workers, queue consumers) the
 * connection is also recoverable: isAlive() probes the stream, reconnect()
 * rebuilds it (re-reading the credential, so token/password rollover is picked
 * up), and ensureLive() combines the two. See RecoverableConnection.
 *
 * The connection carries its owner's userId so it can key itself
 * (see ConnectionKey) and tag the ImapClient's events with account+user
 * context via AccountContextEventDispatcher.
 *
 * @todo POP3: an ImpPop3Connection built the same way against Pop3Client will
 *       cover getProtocol() === 'pop3'. Protocol dispatch (a match on
 *       $account->getProtocol()) belongs at the construction site once that
 *       class exists.
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
final class ImpImapConnection implements ImapConnection, LazyMailboxConnection, RecoverableConnection
{
    /**
     * The protocol client built on first getClient(). Null while the
     * connection is idle.
     */
    private ?ImapClient $client = null;

    /**
     * @param MailboxAccount                $account       The IMAP account to connect to.
     * @param MailboxCredential             $credential    Credential presented at login time.
     * @param string                        $userId        Horde user ID of the account owner. Carried so
     *                                                      the connection can key itself (see ConnectionKey)
     *                                                      and tag AccountBoundImapEvents with their owner.
     * @param EventDispatcherInterface|null $dispatcher    Optional PSR-14 bus handed to the
     *                                                      ImapClient for connection/auth events.
     * @param array|null                    $streamContext Optional PHP stream context passed to the socket
     *                                                      layer, e.g. ['ssl' => ['verify_peer' => false]] to
     *                                                      relax TLS verification. Secure by default (null).
     *                                                      Intended for diagnostics, not request-handling code.
     */
    public function __construct(
        private readonly MailboxAccount $account,
        private readonly MailboxCredential $credential,
        private readonly string $userId,
        private readonly ?EventDispatcherInterface $dispatcher = null,
        private readonly ?array $streamContext = null,
    ) {}

    public function getAccount(): MailboxAccount
    {
        return $this->account;
    }

    /**
     * Horde user ID of the account owner.
     */
    public function getUserId(): string
    {
        return $this->userId;
    }

    /**
     * Composite identity (owner + account) for keying this connection in
     * connection tables, health maps, and caches.
     */
    public function getKey(): ConnectionKey
    {
        return ConnectionKey::of($this->userId, $this->account);
    }

    /**
     * True once the ImapClient has been built (i.e. getClient() was called at
     * least once). False means the connection is idle: no client, no stream,
     * no credential presented.
     *
     * This reflects INITIALIZATION, not liveness — a built client may still
     * hold a dropped socket. Use isAlive() to probe the stream.
     */
    public function isInitialized(): bool
    {
        return $this->client !== null;
    }

    /**
     * The ImapClient for this account, built on first call.
     *
     * Building the client opens no socket; the stream is opened lazily on the
     * first protocol command, and authentication only on an explicit login().
     * Returns the concrete ImapClient (a covariant narrowing of the
     * ImapConnection::getClient(): ImapProtocol contract) so callers can reach
     * login().
     *
     * Note: this does NOT verify liveness. Long-running callers should prefer
     * ensureLive(), which reconnects a dropped stream first.
     */
    public function getClient(): ImapClient
    {
        if ($this->client === null) {
            $this->client = $this->buildClient();
        }

        return $this->client;
    }

    /**
     * Probe whether the stream is open and authenticated right now.
     *
     * Returns false without side effects when the client was never built. When
     * a client exists, sends a NOOP and reports success. A NOOP against a
     * dropped socket surfaces a connection/socket error, which is caught and
     * reported as not-alive; the dead client is discarded so the next
     * ensureLive()/reconnect() rebuilds cleanly rather than reusing a client
     * that ImapClient::noop() may have silently reopened WITHOUT re-login.
     */
    public function isAlive(): bool
    {
        if ($this->client === null) {
            return false;
        }

        try {
            $this->client->noop();

            return true;
        } catch (ConnectionException | SocketException) {
            // The stream is gone. noop() calls connect() internally, so it may
            // have opened a fresh but UNAUTHENTICATED socket before failing (or
            // left one open on a partial failure). Drop it so we never hand a
            // half-open, unauthenticated client back to a caller.
            $this->client = null;

            return false;
        }
    }

    /**
     * Tear down any existing stream and establish a fresh authenticated one.
     *
     * Rebuilds the client from scratch, which re-reads the credential — so a
     * refreshed OAuth token or a rotated password takes effect here. This is
     * the single recovery path for both a dropped socket and a rolled-over
     * credential.
     */
    public function reconnect(): void
    {
        $this->disconnect();
        $this->client = $this->buildClient();
        $this->client->login();
    }

    /**
     * Return a client that is guaranteed live and authenticated, reconnecting
     * if the current stream is dead or was never opened.
     *
     * The daemon-friendly accessor: call it at the top of each unit of work
     * instead of getClient(), so transient drops heal without the caller
     * writing catch-and-rebuild loops.
     */
    public function ensureLive(): ImapClient
    {
        if (!$this->isAlive()) {
            $this->reconnect();
        }

        return $this->client;
    }

    /**
     * Logs out and closes the underlying IMAP stream, returning the connection
     * to its idle state so a later getClient() re-opens it. No-op when the
     * client was never built.
     */
    public function disconnect(): void
    {
        $this->client?->logout();
        $this->client = null;
    }

    /**
     * Build the ImapClient from the account's connection parameters and the
     * credential. Does not connect or authenticate.
     *
     * @throws \LogicException  When the account is not an IMAP account.
     * @throws \RuntimeException When the credential is not in a usable state.
     */
    private function buildClient(): ImapClient
    {
        if ($this->account->getProtocol() !== 'imap') {
            // @todo POP3 accounts belong on an ImpPop3Connection; this class is
            //       IMAP-only until that counterpart exists.
            throw new \LogicException(sprintf(
                'ImpImapConnection cannot serve protocol "%s" for account "%s".',
                $this->account->getProtocol(),
                $this->account->getId(),
            ));
        }

        if ($this->credential->getState() !== HasCredentialsState::Present) {
            throw new \RuntimeException(sprintf(
                'No usable credential for account "%s" (state: %s).',
                $this->account->getId(),
                $this->credential->getState()->name,
            ));
        }

        $config = new ConnectionConfig(
            hostspec: $this->account->getHostspec(),
            port:     $this->account->getPort(),
            secure:   $this->mapSecure($this->account->getSecure()),
            context:  $this->streamContext,
        );

        // @todo A future --insecure opt-in would pass
        //       saslPolicy: SaslPolicy::legacyCompatible() here so plaintext
        //       SASL against a non-TLS dev server is permitted. The default
        //       (secureDefaults) denies PLAIN/LOGIN without TLS.
        return new ImapClient(
            $config,
            $this->credential->getSaslCredentials(),
            $this->wrapDispatcher(),
        );
    }

    /**
     * Wrap the application dispatcher so the ImapClient's events arrive on the
     * application bus tagged with this connection's account and user. Returns
     * null when no dispatcher was supplied (the ImapClient then dispatches
     * nothing).
     */
    private function wrapDispatcher(): ?EventDispatcherInterface
    {
        if ($this->dispatcher === null) {
            return null;
        }

        return new AccountContextEventDispatcher(
            $this->dispatcher,
            $this->account->getId(),
            $this->userId,
        );
    }

    /**
     * Map the account's transport-security token to a SecureMode.
     *
     * SecureMode is a backed string enum, so false (no encryption) must be
     * mapped explicitly rather than passed to SecureMode::from().
     *
     * @param string|false $secure 'ssl', 'tls', 'tlsv1', or false.
     */
    private function mapSecure(string|false $secure): SecureMode
    {
        return match ($secure) {
            false   => SecureMode::None,
            'ssl'   => SecureMode::Ssl,
            'tls'   => SecureMode::Tls,
            'tlsv1' => SecureMode::Tlsv1,
        };
    }
}
