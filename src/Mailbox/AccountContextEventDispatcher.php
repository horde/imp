<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Imap\Client\Event\ImapEvent;
use Psr\EventDispatcher\EventDispatcherInterface;

/**
 * Wraps the imap_client library's event bus and re-dispatches every ImapEvent
 * on the application bus as an AccountBoundImapEvent, adding the accountId and
 * userId that the library events lack.
 *
 * horde/imap_client dispatches typed ImapEvent subclasses carrying only a
 * message and an untyped context array. Application listeners (health
 * tracking, per-account diagnostics) need to know WHICH account and user the
 * event belongs to. A connection hands one of these to its ImapClient so the
 * account context available at construction travels with every event the
 * client emits.
 *
 * Events that are not ImapEvents pass through to the application bus unchanged,
 * so this can safely sit in front of a shared dispatcher.
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
final class AccountContextEventDispatcher implements EventDispatcherInterface
{
    /**
     * @param EventDispatcherInterface $applicationBus The bus AccountBoundImapEvents (and
     *                                                 pass-through events) are dispatched on.
     * @param string                   $accountId      Stable account ID from MailboxAccount::getId().
     * @param string                   $userId         Horde user ID of the account owner.
     */
    public function __construct(
        private readonly EventDispatcherInterface $applicationBus,
        private readonly string $accountId,
        private readonly string $userId,
    ) {}

    /**
     * Re-dispatch ImapEvents wrapped with account context; pass everything else
     * through untouched.
     */
    public function dispatch(object $event): object
    {
        if ($event instanceof ImapEvent) {
            $this->applicationBus->dispatch(
                new AccountBoundImapEvent($this->accountId, $this->userId, $event)
            );

            return $event;
        }

        return $this->applicationBus->dispatch($event);
    }
}
