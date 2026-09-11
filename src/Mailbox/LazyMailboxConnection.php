<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Marker interface for MailboxConnection implementations that defer protocol
 * client construction until the first data-access call.
 *
 * Laziness is a meaningful and discoverable property of certain connection
 * handles. Callers that need to observe whether a connection has been touched
 * (e.g. folder-tree renderers deciding whether to show an active indicator,
 * diagnostic tools, health monitors) check:
 *
 *   if ($connection instanceof LazyMailboxConnection && !$connection->isInitialized()) {
 *       // connection exists but no stream has been opened yet
 *   }
 *
 * Concrete implementations that are NOT lazy (pre-opened admin connections,
 * test doubles with an already-live client) do not implement this interface.
 * Their connections are always initialized by definition.
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
interface LazyMailboxConnection extends MailboxConnection
{
    /**
     * Returns true if the underlying protocol client has been instantiated.
     *
     * False means the connection is idle — no stream has been opened and no
     * credential has been presented to the server. It does NOT mean the
     * account is unreachable.
     *
     * True means at least one connect attempt has been made. The stream may
     * still drop later; this method reflects initialization state, not
     * current liveness.
     */
    public function isInitialized(): bool;
}
