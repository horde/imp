<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Imap\Client\ImapProtocol;

/**
 * Type marker and accessor interface for MailboxConnections backed by an
 * IMAP protocol client.
 *
 * Callers that receive a MailboxConnection and need to branch on IMAP
 * behaviour use instanceof ImapConnection. getClient() exposes the
 * underlying protocol client for callers that need to issue IMAP commands
 * directly.
 *
 * The IMAP operation surface (folder listing, message fetching, flag
 * management, etc.) is deliberately deferred — it belongs in a higher-level
 * abstraction that has not been designed yet.
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
interface ImapConnection extends MailboxConnection
{
    /**
     * The authenticated IMAP client. Use for IMAP protocol operations.
     */
    public function getClient(): ImapProtocol;
}
