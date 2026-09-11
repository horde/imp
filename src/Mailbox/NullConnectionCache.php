<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * No-op ConnectionCache for stateless and test contexts.
 *
 * Every get() is a miss. set() and invalidate() are silent no-ops.
 * Used as the default cache when no explicit implementation is provided,
 * ensuring ImpMailboxAccountManager works without a real cache being wired.
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
final class NullConnectionCache implements ConnectionCache
{
    public function get(string $accountId): ?MailboxConnection
    {
        return null;
    }

    public function set(string $accountId, MailboxConnection $connection): void
    {
    }

    public function invalidate(string $accountId): void
    {
    }
}
