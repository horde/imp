<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

namespace Horde\IMP\Test\Unit\Mailbox;

use Horde\Core\Auth\AuthCredentialStore;
use Horde\Core\Auth\CredentialResult;
use Horde\Core\Auth\HasCredentialsState;
use Horde\Core\Session\SessionAccess;
use Horde\Imp\Mailbox\MailboxAccount;
use Horde\Imp\Mailbox\SessionCredentialSource;
use Horde\Imp\Mailbox\SessionPasswordCredential;
use Horde\Sasl\Credentials\PasswordCredentials;
use PHPUnit\Framework\TestCase;
use RuntimeException;

/**
 * Tests the session-backed federated credential path: SessionPasswordCredential
 * (reads AuthCredentialStore, maps state, builds SASL credentials) and
 * SessionCredentialSource (resolves owner + per-account credential).
 *
 * @coversNothing
 */
class SessionCredentialTest extends TestCase
{
    /**
     * getState() reflects whatever the credential store reports, read live.
     */
    public function testStatePassesThroughFromStore(): void
    {
        $store = $this->storeReturning(
            HasCredentialsState::NeverHad,
            new CredentialResult(HasCredentialsState::NeverHad)
        );
        $cred = new SessionPasswordCredential($store, 'site_backends:abc', 'imp');

        $this->assertSame('site_backends:abc', $cred->getAccountId());
        $this->assertSame(HasCredentialsState::NeverHad, $cred->getState());
    }

    /**
     * When Present, getSaslCredentials() builds PasswordCredentials whose
     * authcid is the stored mailbox userId (NOT the account id or Horde uid).
     */
    public function testPresentBuildsPasswordCredentialsFromStoredUserId(): void
    {
        $store = $this->storeReturning(
            HasCredentialsState::Present,
            new CredentialResult(
                HasCredentialsState::Present,
                ['userId' => 'victim', 'password' => 's3cr3t', 'server' => 'imap']
            )
        );
        $cred = new SessionPasswordCredential($store, 'site_backends:abc', 'imp');

        $sasl = $cred->getSaslCredentials();
        $this->assertInstanceOf(PasswordCredentials::class, $sasl);
        $this->assertSame('victim', $sasl->authcid());
        $this->assertSame('s3cr3t', $sasl->password()->reveal());
    }

    /**
     * getSaslCredentials() throws rather than returning stale credentials when
     * the session password is not Present.
     */
    public function testNotPresentThrowsInsteadOfReturningStaleCredentials(): void
    {
        $store = $this->storeReturning(
            HasCredentialsState::NeverHad,
            new CredentialResult(HasCredentialsState::NeverHad)
        );
        $cred = new SessionPasswordCredential($store, 'site_backends:abc', 'imp');

        $this->expectException(RuntimeException::class);
        $cred->getSaslCredentials();
    }

    /**
     * A Present result that is missing the password key is treated as
     * unusable, not silently degraded.
     */
    public function testPresentButMissingPasswordKeyThrows(): void
    {
        $store = $this->storeReturning(
            HasCredentialsState::Present,
            new CredentialResult(
                HasCredentialsState::Present,
                ['userId' => 'victim']
            )
        );
        $cred = new SessionPasswordCredential($store, 'site_backends:abc', 'imp');

        $this->expectException(RuntimeException::class);
        $cred->getSaslCredentials();
    }

    /**
     * The source reports the connection owner from the session auth id.
     */
    public function testSourceOwnerIsSessionAuthId(): void
    {
        $session = $this->createMock(SessionAccess::class);
        $session->expects($this->once())
            ->method('getAuthId')
            ->willReturn('horde-user');

        $store = $this->storeReturning(
            HasCredentialsState::NeverHad,
            new CredentialResult(HasCredentialsState::NeverHad)
        );

        $source = new SessionCredentialSource($session, $store, 'imp');
        $this->assertSame('horde-user', $source->ownerUserId());
    }

    /**
     * resolve() yields a credential bound to the requested account id.
     */
    public function testSourceResolvesCredentialForAccount(): void
    {
        $session = $this->createStub(SessionAccess::class);
        $store = $this->storeReturning(
            HasCredentialsState::Present,
            new CredentialResult(
                HasCredentialsState::Present,
                ['userId' => 'victim', 'password' => 's3cr3t']
            )
        );

        $account = $this->createStub(MailboxAccount::class);
        $account->method('getId')->willReturn('site_backends:abc');

        $source = new SessionCredentialSource($session, $store, 'imp');
        $cred = $source->resolve($account);

        $this->assertSame('site_backends:abc', $cred->getAccountId());
        $this->assertSame('victim', $cred->getSaslCredentials()->authcid());
    }

    /**
     * Build an AuthCredentialStore double that returns fixed state/result.
     *
     * AuthCredentialStore is not final and its two read methods are the only
     * ones the credential touches.
     */
    private function storeReturning(
        HasCredentialsState $state,
        CredentialResult $result
    ): AuthCredentialStore {
        return new class ($state, $result) extends AuthCredentialStore {
            public function __construct(
                private readonly HasCredentialsState $fixedState,
                private readonly CredentialResult $fixedResult,
            ) {
                // Intentionally does not call parent::__construct(): this double
                // never touches a session.
            }

            public function getState(string $app): HasCredentialsState
            {
                return $this->fixedState;
            }

            public function getOrExplain(string $app): CredentialResult
            {
                return $this->fixedResult;
            }
        };
    }
}
