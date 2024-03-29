<?php
/**
 * Copyright 2013-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Iterator that returns all the ancestors (and their siblings) for an
 * element.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Ftree_Iterator_Ancestors extends IMP_Ftree_Iterator
{
    /**
     */
    public function __construct($elt)
    {
        $elts = array();

        while ($elt && ($elt = $elt->parent)) {
            $elts = array_merge($elt->child_list, $elts);
        }

        parent::__construct($elts);
    }

    /**
     */
    #[\ReturnTypeWillChange]
    public function getChildren()
    {
        return new self(array());
    }

    /**
     */
    public function hasChildren(): bool
    {
        return false;
    }

}
