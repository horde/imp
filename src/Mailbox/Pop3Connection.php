<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Imap\Client\Pop3Client;

/**
 * POP3 accounts carry the MailboxCapability::DownloadOnly capability: no
 * server-side folder management, no SEARCH, no flag persistence, no APPEND.
 * Callers that need to branch on POP3 behaviour use instanceof Pop3Connection
 * or check for MailboxCapability::DownloadOnly on the account.
 * getClient() exposes the underlying Pop3Client for direct POP3 operations.
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
interface Pop3Connection extends MailboxConnection
{
    /**
     * The authenticated POP3 client. Use for POP3 protocol operations.
     */
    public function getClient(): Pop3Client;
}
