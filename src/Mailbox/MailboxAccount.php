<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Describes a configured mailbox account.
 *
 * A MailboxAccount is a pure descriptor — it carries no live connection
 * state and may be safely serialised, compared, and cached across requests.
 * Concrete classes (ImapAccount, Pop3Account, etc.) extend this interface;
 * the class itself carries the account kind.
 *
 * Account identity is scoped to a user: two users may hold accounts with the
 * same ID without conflict.
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
interface MailboxAccount
{
    /**
     * Stable, user-scoped identifier for this account.
     *
     * Format convention: '{providerId}:{hash(host+username)}'.
     * Must be unique within a user's account set and stable across requests
     * so that connection caches, UI state, and per-account config maps can
     * key on it reliably.
     */
    public function getId(): string;

    /**
     * Human-readable display label for this account.
     */
    public function getLabel(): string;

    /**
     * Identifier of the MailboxProvider that created this account.
     *
     * Used to locate a specific account without relying on positional
     * ordering (e.g. query by providerId == 'session_credential').
     */
    public function getProviderId(): string;

    /**
     * Full capability set declared for this account.
     *
     * Tier-1 capabilities reflect the protocol of the concrete account class.
     * Tier-2 capabilities depend on the provider/credential combination and
     * may vary between accounts emitted by the same provider.
     *
     * hasCapability($cap) is not on this interface — derive it from this
     * collection via a trait or abstract base when call-site pressure exists.
     *
     * @return MailboxCapability[]
     */
    public function getCapabilities(): array;

    /**
     * Wire-level protocol identifier: 'imap' or 'pop3'.
     *
     * Connectors and connections dispatch on this to pick the protocol client.
     */
    public function getProtocol(): string;

    /**
     * Mail server hostname or IP address to connect to.
     */
    public function getHostspec(): string;

    /**
     * TCP port, or null when the protocol default should be used
     * (993/143 for IMAP, 995/110 for POP3).
     */
    public function getPort(): ?int;

    /**
     * Transport security: 'ssl', 'tls', 'tlsv1', or false for no encryption.
     *
     * @return string|false
     */
    public function getSecure(): string|false;
}
