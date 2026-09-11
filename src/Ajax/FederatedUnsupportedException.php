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

namespace Horde\Imp\Ajax;

use IMP_Exception;

/**
 * Raised when an AJAX action is invoked in federated app-auth mode but its
 * backend behavior has not yet been wired to the federated account layer.
 *
 * This is distinct from Horde_Exception_AuthenticationFailure: the user IS
 * authenticated. The action simply reaches legacy IMAP code (the base
 * IMP_Imap acquired through IMP_Factory_Imap::create()) that assumes a
 * connected "main account", which federated mode does not provide. Throwing
 * this instead of letting the request fall through to the legacy
 * IMP_Imap::__call() guard keeps the failure correctly attributed: the client
 * sees a handled action error rather than a spurious session-timeout/logout.
 *
 * Extends IMP_Exception (and thus Horde_Exception) so the modern AJAX
 * dispatch path catches it and builds a graceful response envelope rather
 * than surfacing a bare 500.
 *
 * @author    Ralf Lang <ralf.lang@ralf-lang.de>
 * @category  Horde
 * @copyright 2026 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class FederatedUnsupportedException extends IMP_Exception
{
    /**
     * The AJAX action that is not yet supported in federated mode.
     *
     * @var string
     */
    public string $action;

    /**
     * Constructor.
     *
     * @param string $action  The AJAX action name that reached legacy
     *                         backend code in federated mode.
     */
    public function __construct(string $action)
    {
        $this->action = $action;

        parent::__construct(sprintf(
            'The "%s" action is not yet available when IMP runs in federated mode.',
            $action
        ));
    }
}
