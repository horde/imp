<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Session\SessionAccess;

/**
 * Credential source backed by the session credential store.
 *
 * Resolves the connection owner from the authenticated Horde session
 * (SessionAccess::getAuthId()) and represents each account with a
 * SessionPasswordCredential reading the application's session credential slot.
 *
 * This is the first federated credential path: it works whenever a login flow
 * has stashed the mailbox username/password in the encrypted session slot for
 * the application. Until such a flow runs, the produced credential reports
 * NeverHad and the connection stays unauthenticated but resolvable — login is
 * lazy and explicit, so resolution itself neither connects nor fails.
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
final class SessionCredentialSource implements MailboxCredentialSource
{
    /**
     * @param SessionAccess       $session The request-scoped session accessor.
     * @param AuthCredentialStore $store   Session-resident credential store.
     * @param string              $app     The application whose session
     *                                      credential slot holds the mailbox
     *                                      username/password. Defaults to 'imp'.
     */
    public function __construct(
        private readonly SessionAccess $session,
        private readonly AuthCredentialStore $store,
        private readonly string $app = 'imp',
    ) {}

    public function ownerUserId(): ?string
    {
        return $this->session->getAuthId();
    }

    public function resolve(MailboxAccount $account): MailboxCredential
    {
        return new SessionPasswordCredential(
            $this->store,
            $account->getId(),
            $this->app
        );
    }
}
