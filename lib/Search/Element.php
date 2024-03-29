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
 * Abstract framework for a search query element.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2010-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
abstract class IMP_Search_Element implements Serializable
{
    /* Serialized version. */
    const VERSION = 1;

    /**
     * Allow NOT search on this element?
     *
     * @var boolean
     */
    public $not = true;

    /**
     * Data for this element.
     *
     * @var object
     */
    protected $_data;

    /**
     * Adds the current query item to the query object.
     *
     * @param string $mbox                             The mailbox to create
     *                                                 the query for.
     * @param Horde_Imap_Client_Search_Query $queryob  The query object.
     *
     * @return Horde_Imap_Client_Search_Query  The altered query object.
     */
    abstract public function createQuery($mbox, $queryob);

    /**
     * Return search query text representation.
     *
     * @return array  The textual description of this search element.
     */
    abstract public function queryText();

    /**
     * Returns the criteria data for the element.
     *
     * @return object  The criteria (see each class for the available
     *                 properties).
     */
    public function getCriteria()
    {
        return $this->_data;
    }

    /* Serializable methods. */

    /**
     * Serialization.
     *
     * @return string  Serialized data.
     */
    public function serialize()
    {
        return array_shift($this->__serialize()); 
    }
    public function __serialize(): array 
    {
        return
        [
            json_encode(array(
                self::VERSION,
                $this->_data
            ))
        ];
    }

    /**
     * Unserialization.
     *
     * @param string $data  Serialized data.
     *
     * @throws Exception
     */
    public function unserialize($data)
    {
        $this->__unserialize([$data]);
    }
    public function __unserialize(array $data): void 
    {
        $data = json_decode($data[0]);
        if (!is_array($data) ||
            !isset($data[0]) ||
            ($data[0] != self::VERSION)) {
            throw new Exception('Cache version change');
        }

        $this->_data = $data[1];
    }
}
