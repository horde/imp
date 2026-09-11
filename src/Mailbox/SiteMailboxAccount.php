<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * A MailboxAccount backed by a named entry in the site backends.php
 * configuration.
 *
 * The account ID is stable and user-scoped: two users may independently
 * hold a SiteMailboxAccount with the same backend key without conflict,
 * because the manager is always request-scoped to a single authenticated
 * user.
 *
 * Capabilities are derived from the configured protocol at construction
 * time. They reflect the application-level surface that can be offered
 * assuming a working connection; they are not verified against the live
 * server.
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
final class SiteMailboxAccount implements MailboxAccount
{
    /**
     * @param string                   $id           Stable account ID: 'site_backends:{backendKey}'.
     * @param string                   $label        Display name from backends.php 'name', or the key itself.
     * @param string                   $backendKey   The key as it appears in backends.php.
     * @param string                   $protocol     'imap' or 'pop3'.
     * @param list<MailboxCapability>  $capabilities Capabilities inferred from protocol.
     * @param string                   $hostspec     Mail server hostname or IP address.
     * @param int|null                 $port         TCP port, or null to use the protocol default.
     * @param string|false             $secure       'ssl', 'tls', 'tlsv1', or false for no encryption.
     */
    public function __construct(
        private readonly string $id,
        private readonly string $label,
        private readonly string $backendKey,
        private readonly string $protocol,
        private readonly array $capabilities,
        private readonly string $hostspec = 'localhost',
        private readonly int|null $port = null,
        private readonly string|false $secure = 'tls',
    ) {}

    public function getId(): string
    {
        return $this->id;
    }

    public function getLabel(): string
    {
        return $this->label;
    }

    public function getProviderId(): string
    {
        return SiteAccountsProvider::PROVIDER_ID;
    }

    /**
     * @return list<MailboxCapability>
     */
    public function getCapabilities(): array
    {
        return $this->capabilities;
    }

    /**
     * The backends.php key this account was created from.
     */
    public function getBackendKey(): string
    {
        return $this->backendKey;
    }

    /**
     * Protocol identifier: 'imap' or 'pop3'.
     */
    public function getProtocol(): string
    {
        return $this->protocol;
    }

    /**
     * Mail server hostname or IP address.
     */
    public function getHostspec(): string
    {
        return $this->hostspec;
    }

    /**
     * TCP port, or null when the protocol default should be used.
     */
    public function getPort(): ?int
    {
        return $this->port;
    }

    /**
     * Transport security: 'ssl', 'tls', 'tlsv1', or false for no encryption.
     *
     * @return string|false
     */
    public function getSecure(): string|false
    {
        return $this->secure;
    }
}
