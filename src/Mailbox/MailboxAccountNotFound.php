<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Thrown when no MailboxProvider resolves an account for the requested ID.
 *
 * Raised by MailboxAccountManager::getAccount() and getConnectionById()
 * when the given account ID is not emitted by any registered provider for
 * the current context.
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
final class MailboxAccountNotFound extends \RuntimeException
{
    public function __construct(
        private readonly string $accountId,
        ?\Throwable $previous = null,
    ) {
        parent::__construct(
            sprintf(
                'No mailbox account with ID "%s" was resolved by any registered provider.',
                $accountId
            ),
            0,
            $previous
        );
    }

    /**
     * The account ID that could not be resolved.
     *
     * Available for callers that need programmatic access to the ID without
     * parsing the exception message.
     */
    public function getAccountId(): string
    {
        return $this->accountId;
    }
}
