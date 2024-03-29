<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

use Symfony\Component\Console\Input\ArrayInput;

/**
 * Provides an interface to query a mailbox's settable permanent flags.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2011-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Imap_PermanentFlags implements IteratorAggregate
{
    /* IMAP flag indicating flags can be created in mailbox. */
    const CREATE = "\\*";

    /**
     * Can new flags NOT be created?
     *
     * @var boolean
     */
    protected $_nocreate = false;

    /**
     * List of unsettable flags.
     *
     * @var array
     */
    protected $_noset = array();

    /**
     * List of settable flags.
     *
     * @var array
     */
    protected $_set = array();

    /**
     * Constructor.
     *
     * @param array $permflags  List of permanent flags in mailbox.
     * @param array $flags      List of flags in mailbox.
     */
    public function __construct(array $permflags = array(),
                                array $flags = array())
    {
        $this->_nocreate = !in_array(self::CREATE, $permflags);
        $this->_noset = array_diff($permflags, $flags, array(self::CREATE));
        $this->_set = array_intersect($permflags, $flags);
    }

    /**
     * Determines if the given flag is allowed to be changed permanently.
     *
     * @param string $flag  The flag to query.
     *
     * @return boolean  True if flag can be set permanently.
     */
    public function allowed($flag)
    {
        $flag = strtolower($flag);
        return (!in_array($flag, $this->_noset) &&
                (!$this->_nocreate || in_array($flag, $this->_set)));
    }

    /* IteratorAggregate method. */

    public function getIterator(): ArrayIterator
    {
        return new ArrayIterator($this->_set);
    }

}
