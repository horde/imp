<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Base definition for built-in Virtual Folders.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
abstract class IMP_Search_Vfolder_Builtin extends IMP_Search_Vfolder
{
    /**
     * Can this query be edited?
     *
     * @var boolean
     */
    protected $_canEdit = false;

    /**
     * List of serialize entries not to save.
     *
     * @var array
     */
    protected $_nosave = array('c', 'i', 'l', 'm');

    /**
     * Constructor.
     *
     * The 'add', 'id', 'label', and 'mboxes' parameters are not honored.
     *
     * @see __construct()
     */
    public function __construct(array $opts = array())
    {
        $this->enabled = empty($opts['disable']);

        $this->_init();
    }

    /**
     * Initialization tasks.
     */
    abstract protected function _init();

    /**
     * Unserialization.
     *
     * @param string $data  Serialized data.
     *
     * @throws Exception
     */
    public function unserialize($data)
    {
        parent::unserialize($data);
        $this->_init();
    }
    public function __unserialize(array $data): void 
    {
        parent::__unserialize($data);
        $this->_init();
    }
}
