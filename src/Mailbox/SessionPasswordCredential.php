<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Auth\HasCredentialsState;
use Horde\Sasl\Credentials\Credentials;
use Horde\Sasl\Credentials\PasswordCredentials;
use Horde\Sasl\Credentials\PlainSecret;
use RuntimeException;

/**
 * A MailboxCredential backed by the session-resident credential store.
 *
 * Reads the IMAP username and password that a login flow stashed in the
 * encrypted session slot for an application, via
 * Horde\Core\Auth\AuthCredentialStore. State and credentials are re-read from
 * the store on demand rather than captured at construction, so the credential
 * reflects the live session: a password wiped mid-session (idle wipe, logout
 * in another tab) surfaces as a non-Present state instead of a stale value.
 *
 * The stored credentials array follows the convention shared with traditional
 * IMP login: 'userId' (the mailbox username) and 'password' (cleartext,
 * held only in the encrypted session). The mailbox username in that array is
 * used as the SASL authcid; it is distinct from the Horde auth id that owns
 * the connection, which the account manager tracks separately.
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
final class SessionPasswordCredential implements MailboxCredential
{
    /**
     * @param AuthCredentialStore $store     Session-resident credential store.
     * @param string              $accountId The account ID this credential
     *                                        authenticates. Must match the
     *                                        target MailboxAccount::getId().
     * @param string              $app        The application whose session
     *                                        credential slot holds the mailbox
     *                                        username/password (e.g. 'imp').
     */
    public function __construct(
        private readonly AuthCredentialStore $store,
        private readonly string $accountId,
        private readonly string $app,
    ) {}

    public function getAccountId(): string
    {
        return $this->accountId;
    }

    /**
     * Reflects the live session credential state for the application. Read
     * from AuthCredentialStore on every call so a mid-session change (wipe,
     * invalidation) is observed rather than cached.
     */
    public function getState(): HasCredentialsState
    {
        return $this->store->getState($this->app);
    }

    /**
     * Builds PasswordCredentials from the session-stored username and password.
     *
     * Re-reads the store rather than trusting a construction-time snapshot: if
     * the session password is no longer Present, throws instead of returning
     * stale credentials, per the MailboxCredential contract.
     *
     * @throws RuntimeException if the credential is not Present, or the stored
     *                          array is missing the expected keys.
     */
    public function getSaslCredentials(): Credentials
    {
        $result = $this->store->getOrExplain($this->app);

        if ($result->state !== HasCredentialsState::Present
            || $result->credentials === null) {
            throw new RuntimeException(sprintf(
                'No session password available for account "%s" (state: %s).',
                $this->accountId,
                $result->state->value
            ));
        }

        $credentials = $result->credentials;
        if (!isset($credentials['userId'], $credentials['password'])
            || !is_string($credentials['password'])) {
            throw new RuntimeException(sprintf(
                'Session credential for account "%s" is missing a usable '
                    . 'username/password pair.',
                $this->accountId
            ));
        }

        return new PasswordCredentials(
            (string) $credentials['userId'],
            new PlainSecret($credentials['password'])
        );
    }
}
