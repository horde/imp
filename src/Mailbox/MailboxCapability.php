<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Application-level capability tokens for MailboxAccount instances.
 *
 * Each case describes what the application can DO with an account,
 * not which protocol feature or server extension underlies it.
 * A connector providing efficient search via PHP-side indexing declares
 * EfficientSearch just as one backed by IMAP SEARCH does.
 *
 * The set is intentionally small. Add cases only when a concrete
 * application decision depends on them.
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
enum MailboxCapability: string
{
    /** App can browse and manage a folder tree: create, rename, delete, subscribe. */
    case FolderManagement = 'folder_management';

    /** App can delegate search and filter to the backend rather than fetch-and-filter in PHP. */
    case EfficientSearch = 'efficient_search';

    /** App can receive new-message notifications without polling. */
    case PushNotification = 'push_notification';

    /** App can set and clear per-message flags (Seen, Flagged, Answered, etc.). */
    case MessageFlags = 'message_flags';

    /** App can copy or upload messages into this account (save-sent, import, cross-account move). */
    case MessageUpload = 'message_upload';

    /**
     * Account is entirely read-only: no writes, no flag changes, no folder management.
     * Applies to ACL-restricted IMAP and feed-type accounts.
     * POP3 is NOT read-only (DELE allows message removal); detect POP3 via instanceof Pop3Connection.
     */
    case ReadOnly = 'read_only';

    /** App can access this account without a live user session (background jobs, cron, filters). */
    case BackgroundAuth = 'background_auth';

    /** Account authenticates via OAuth2 bearer token (XOAUTH2 SASL); connector uses OAuthTokenService. */
    case OAuthBearer = 'oauth_bearer';
}
