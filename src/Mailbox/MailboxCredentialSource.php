<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Resolves a MailboxCredential and the owning Horde user for a request.
 *
 * This is the seam MailboxAccountManager::getConnection() uses to turn an
 * account descriptor into a live (but lazy) connection without the caller
 * supplying credentials explicitly. Implementations pull from whatever the
 * deployment authenticates with: the session credential store, an OAuth token
 * service, or another mechanism. Each mechanism is a separate implementation
 * so the manager stays agnostic.
 *
 * Resolution never logs in and never blocks on the backend. It only decides
 * WHICH credential object represents this account for this request; whether
 * that credential is usable is expressed through MailboxCredential::getState(),
 * checked at the explicit, lazy login moment.
 *
 * Copyright 2026 The Horde Project (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 The Horde Project
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
interface MailboxCredentialSource
{
    /**
     * The Horde user id that owns connections in this request, or null when
     * no authenticated user is available.
     *
     * This identifies the connection owner for cache-keying and is distinct
     * from the mailbox username carried inside the credential.
     */
    public function ownerUserId(): ?string;

    /**
     * Produce the MailboxCredential representing the given account for the
     * current request.
     *
     * Returns a credential whose getState() may be NeverHad or Invalidated
     * when no usable secret exists yet; it does not throw for a missing
     * secret. The returned credential's getAccountId() matches the account.
     *
     * @param MailboxAccount $account The account to authenticate.
     */
    public function resolve(MailboxAccount $account): MailboxCredential;
}
