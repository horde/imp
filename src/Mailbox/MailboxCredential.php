<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Auth\HasCredentialsState;
use Horde\Sasl\Credentials\Credentials;

/**
 * Live credential for a specific mailbox account.
 *
 * Credentials have a lifecycle independent of the account they authenticate:
 * they may not yet exist when the account is first discovered, and they may
 * become invalid (token expiry, password change, session end) while the
 * account descriptor remains perfectly valid.
 *
 * MailboxCredential is the application-level lifecycle wrapper around
 * Horde\Sasl\Credentials. Connectors call getSaslCredentials() at connect
 * time to obtain a currently valid Horde\Sasl\Credentials instance.
 * Callers MUST check getState() before calling getSaslCredentials() and
 * handle non-Present states appropriately rather than attempting a doomed
 * connection.
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
interface MailboxCredential
{
    /**
     * The account ID this credential authenticates.
     *
     * Matches MailboxAccount::getId() for the account this credential was
     * issued for.
     */
    public function getAccountId(): string;

    /**
     * Current lifecycle state of this credential.
     *
     * Mirrors Horde\Core\Auth\HasCredentialsState semantics:
     *
     *   Present     — credential exists and a connection attempt can proceed.
     *   NeverHad    — account is known but credentials have not yet been
     *                 acquired (OAuth2 flow incomplete, no password entered
     *                 in this session). Present an appropriate auth prompt.
     *   Invalidated — credential existed but was wiped (token revoked,
     *                 password changed, session ended). getSaslCredentials()
     *                 MUST NOT be called in this state.
     *
     * @see \Horde\Core\Auth\InvalidationReason for why Invalidated occurred.
     */
    public function getState(): HasCredentialsState;

    /**
     * Returns a Horde\Sasl\Credentials instance ready for SASL authentication.
     *
     * MUST only be called when getState() returns HasCredentialsState::Present.
     *
     * For OAuth2 / OIDC credentials: obtains a fresh access token, performing
     * a transparent refresh if the current token has expired but a refresh
     * token is available. This call MAY block briefly for a network round-trip.
     *
     * For session-password credentials: returns the stored credentials. No
     * refresh is possible; if the session password is no longer available,
     * implementations MUST throw rather than return stale credentials.
     *
     * @throws \RuntimeException if getState() is not Present
     */
    public function getSaslCredentials(): Credentials;
}
