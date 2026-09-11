<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Imap\Client\Event\ImapEvent;

/**
 * PSR-14 event that wraps an imap_client library event with account context.
 *
 * horde/imap_client dispatches typed ImapEvent subclasses (ConnectionEstablished,
 * AuthenticationSucceeded, AuthenticationFailed, ConnectionClosed, AlertReceived,
 * etc.) on a PSR-14 bus injected into the protocol client. Those library events
 * carry only a message string and an untyped context array but no
 * accountId or userId.
 *
 * AccountContextEventDispatcher intercepts every ImapEvent from the library bus
 * and re-dispatches it on the application bus as an AccountBoundImapEvent,
 * adding the accountId and userId that the connection supplied when it built
 * its client (see ImpImapConnection).
 *
 *
 * Example listener (health tracking):
 *
 *   use Horde\Imap\Client\Event\AuthenticationSucceeded;
 *   use Horde\Imap\Client\Event\AuthenticationFailed;
 *
 *   public function __invoke(AccountBoundImapEvent $event): void
 *   {
 *       match (true) {
 *           $event->innerEvent instanceof AuthenticationSucceeded =>
 *               $this->health->recordSuccess($event->accountId, $event->userId),
 *           $event->innerEvent instanceof AuthenticationFailed =>
 *               $this->health->recordFailure($event->accountId, $event->userId, $event->innerEvent->getMessage()),
 *           default => null,
 *       };
 *   }
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
final readonly class AccountBoundImapEvent
{
    public function __construct(
        /** Stable account ID from MailboxAccount::getId(). */
        public string    $accountId,
        /** Horde user ID of the account owner, supplied by the connector at connect time. */
        public string    $userId,
        /** The original library event. Instanceof-check for specific subtypes. */
        public ImapEvent $innerEvent,
    ) {}
}
