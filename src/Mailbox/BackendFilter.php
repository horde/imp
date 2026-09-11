<?php

declare(strict_types=1);

namespace Horde\Imp\Mailbox;

/**
 * Filter applied to SiteAccountsProvider::list() when inspecting the
 * site backend configuration.
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
enum BackendFilter
{
    /** Only backends that are not marked disabled in backends.php. */
    case Enabled;

    /** Only backends that are explicitly marked disabled in backends.php. */
    case Disabled;

    /** All backend entries regardless of their disabled flag. */
    case All;
}
