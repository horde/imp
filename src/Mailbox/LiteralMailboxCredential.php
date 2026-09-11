<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Auth\HasCredentialsState;
use Horde\Sasl\Credentials\Credentials;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;

/**
 * A MailboxCredential backed by an explicit username and password supplied
 * at construction time.
 *
 * Use this for CLI scripts, integration tests or any context where credentials are
 * provided directly rather than resolved from a session or persistent store.
 *
 * Do NOT use in request-handling code where the credential should come from
 * the session (AuthCredentialStore) or the vault (CredentialVault). Those
 * paths will use their own MailboxCredential implementations once built.
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
final class LiteralMailboxCredential implements MailboxCredential
{
    /**
     * @param string $accountId The account ID this credential authenticates.
     *                          Must match MailboxAccount::getId() for the target account.
     * @param string $username  IMAP/POP3 username. For site backends with hordeauth
     *                          this is typically the authenticated Horde user ID.
     * @param string $password  Cleartext password. Held in memory for the lifetime
     *                          of this object only.
     */
    public function __construct(
        private readonly string $accountId,
        private readonly string $username,
        private readonly string $password,
    ) {}

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * Always returns Present: the credential is available as long as this
     * object exists. There is no invalidation path.
     */
    public function getState(): HasCredentialsState
    {
        return HasCredentialsState::Present;
    }

    /**
     * Returns a PasswordCredentials instance wrapping the stored username
     * and password. Safe to call without checking getState() first, but
     * callers following the MailboxCredential contract will check anyway.
     */
    public function getSaslCredentials(): Credentials
    {
        return new PasswordCredentials($this->username, new PlainSecret($this->password));
    }
}
