<?php
/**
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * A fixed date (read-only) implementation of the sortpref preference.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Prefs_Sort_FixedDate extends IMP_Prefs_Sort_None
{
    #[\ReturnTypeWillChange]
    public function offsetGet($offset): IMP_Prefs_Sort_Sortpref_Locked
    {
        return new IMP_Prefs_Sort_Sortpref_Locked(
            $offset,
            IMP::IMAP_SORT_DATE,
            1
        );
    }

}
