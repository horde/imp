<?php

declare(strict_types=1);

namespace Horde\Imp;

use Horde\Core\Config\ConfigLoader;

/**
 * Reads and exposes the application-level authentication mode from IMP
 * configuration.
 *
 * The mode is set via app_auth_mode in var/config/imp/conf.php. If unset,
 * the default is 'traditional' so that installs that have not yet
 * regenerated conf.php keep classic behaviour.
 *
 * Two modes are defined:
 *   - 'traditional'  Classic IMP-driven login. IMP performs primary IMAP
 *                    authentication and opens the mailbox connection on login.
 *   - 'federated'    External identity provider. IMP receives an already-
 *                    authenticated Horde identity and opens mailbox
 *                    connections on demand per account.
 *
 * Inject this service wherever the current mode needs to be tested. All
 * three callers that previously duplicated the config-read logic now delegate
 * here instead.
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
final class AppAuthMode
{
    public function __construct(
        private readonly ConfigLoader $configLoader,
    ) {}

    // -------------------------------------------------------------------------
    // Accessors
    // -------------------------------------------------------------------------

    /**
     * Returns the raw configured mode string.
     *
     * @return string  'traditional' or 'federated'. Defaults to 'traditional'
     *                 when the key is absent from conf.php.
     */
    public function get(): string
    {
        return $this->configLoader
            ->load('imp')
            ->get('server.app_auth_mode', 'traditional');
    }

    /**
     * Returns true when IMP is operating in federated mode.
     *
     * Federated mode: identity comes from an external provider; IMP does
     * not perform its own application-level authentication.
     */
    public function isFederated(): bool
    {
        return $this->get() === 'federated';
    }

    /**
     * Returns true when IMP is operating in traditional (non-federated) mode.
     *
     * Traditional mode: IMP-driven login; primary IMAP connection is opened
     * eagerly on authentication.
     */
    public function isTraditional(): bool
    {
        return $this->get() === 'traditional';
    }
}
