<?php
/**
 * Copyright 2002-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Object handling mailbox searching in IMP.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Search implements ArrayAccess, IteratorAggregate, Serializable
{
    /* The mailbox search prefix. */
    const MBOX_PREFIX = "impsearch\0";

    /* The special search mailbox names. */
    const FILTERSEARCH = 'impfsearch';
    const QUICKSEARCH = 'impqsearch';

    /* Query creation types. */
    const CREATE_FILTER = 1;
    const CREATE_QUERY = 2;
    const CREATE_VFOLDER = 3;

    /**
     * Has the object data changed?
     *
     * @var boolean
     */
    public $changed = false;

    /**
     * Search queries.
     *
     * Each subarray contains:
     *   Keys: mailbox IDs.
     *   Values: IMP_Search_Query objects.
     *
     * @var array
     */
    protected $_search = array(
        'filters' => array(),
        'query' => array(),
        'vfolders' => array()
    );

    /**
     * Constructor.
     */
    public function __construct()
    {
        $this->init();
    }

    /**
     * Initialize session search data.
     */
    public function init()
    {
        $this->_getFilters();
        $this->_getVFolders();
    }

    /**
     * Creates the IMAP search query.
     *
     * @param array $criteria  The search criteria array.
     * @param array $opts      Additional options:
     *   - id: (string) Use as the mailbox ID.
     *   - label: (string) The label to use for the search results.
     *   - mboxes: (array) The list of mailboxes to directly search. If this
     *             contains the IMP_Search_Query::ALLSEARCH constant, all
     *             mailboxes will be searched.
     *   - subfolders: (array) The list of mailboxes to do subfolder searches
     *                 on.
     *   - type: (integer) Query type.
     *
     * @return IMP_Search_Query  Returns the query object.
     * @throws InvalidArgumentException
     */
    public function createQuery($criteria, array $opts = array())
    {
        global $injector;

        $opts = array_merge(array(
            'id' => null,
            'label' => null,
            'mboxes' => array(),
            'subfolders' => array(),
            'type' => self::CREATE_QUERY
        ), $opts);

        /* Make sure mailbox names are not IMP_Mailbox objects. */
        $opts['mboxes'] = array_map('strval', $opts['mboxes']);
        $opts['subfolders'] = array_map('strval', $opts['subfolders']);

        switch ($opts['type']) {
        case self::CREATE_FILTER:
            $cname = 'IMP_Search_Filter';
            break;

        case self::CREATE_QUERY:
            $cname = 'IMP_Search_Query';
            if (empty($opts['mboxes']) && empty($opts['subfolders'])) {
                throw new InvalidArgumentException('Search query requires at least one mailbox.');
            }
            break;

        case self::CREATE_VFOLDER:
            $cname = 'IMP_Search_Vfolder';
            if (empty($opts['mboxes']) && empty($opts['subfolders'])) {
                throw new InvalidArgumentException('Search query requires at least one mailbox.');
            }
            break;
        }

        $ob = new $cname(array_filter(array(
            'add' => $criteria,
            'all' => in_array(IMP_Search_Query::ALLSEARCH, $opts['mboxes']),
            'id' => $this->_strip($opts['id']),
            'label' => $opts['label'],
            'mboxes' => $opts['mboxes'],
            'subfolders' => $opts['subfolders']
        )));

        switch ($opts['type']) {
        case self::CREATE_FILTER:
            /* This will overwrite previous value, if it exists. */
            $this->_search['filters'][$ob->id] = $ob;
            $this->setFilters($this->_search['filters']);
            break;

        case self::CREATE_QUERY:
            $this->_search['query'][$ob->id] = $ob;
            $ob->mbox_ob->list_ob->rebuild(true);
            break;

        case self::CREATE_VFOLDER:
            /* This will overwrite previous value, if it exists. */
            $this->_search['vfolders'][$ob->id] = $ob;
            $this->setVFolders($this->_search['vfolders']);
            $injector->getInstance('IMP_Mailbox_SessionCache')->expire(
                array(
                    IMP_Mailbox_SessionCache::CACHE_DISPLAY,
                    IMP_Mailbox_SessionCache::CACHE_LABEL
                ),
                $ob->mbox_ob
            );
            $ftree = $injector->getInstance('IMP_Ftree');
            $ftree->delete($ob);
            $ftree->insert($ob);
            break;
        }

        /* Reset the sort direction for system queries. */
        if ($this->isSystemQuery($ob)) {
            $ob->mbox_ob->setSort(null, null, true);
        }

        $this->changed = true;

        return $ob;
    }

    /**
     * Saves the list of filters for the current user.
     *
     * @param array $filters  The filter list.
     */
    public function setFilters($filters)
    {
        $GLOBALS['prefs']->setValue('filter', serialize(array_values($filters)));
        $this->_getFilters();
    }

    /**
     * Loads the list of filters for the current user.
     */
    protected function _getFilters()
    {
        $filters = array();

        /* Build list of default filters. */
        $di = new DirectoryIterator(IMP_BASE . '/lib/Search/Filter');
        foreach ($di as $val) {
            if ($val->isFile()) {
                $cname = 'IMP_Search_Filter_' . $val->getBasename('.php');
                if (($cname != 'IMP_Search_Filter_Builtin') &&
                    class_exists($cname)) {
                    $filter = new $cname();
                    $filters[$filter->id] = $filter;
                }
            }
        }

        if ($f_list = $GLOBALS['prefs']->getValue('filter')) {
            $f_list = @unserialize($f_list, array('allowed_classes' => array(
                'IMP_Search_Filter',
                'IMP_Search_Filter_Personal',
                'IMP_Search_Filter_Attachment',
                'IMP_Search_Filter_Autogenerated',
                'IMP_Search_Filter_Contacts',
                'IMP_Search_Filter_Bulk',
                'IMP_Search_Filter_Mailinglist',
            )));
            if (is_array($f_list)) {
                foreach ($f_list as $val) {
                    if ($val instanceof IMP_Search_Filter) {
                        $filters[$val->id] = $val;
                    }
                }
            }
        }

        $this->_search['filters'] = $filters;
        $this->changed = true;
    }

    /**
     * Is a mailbox a filter query?
     *
     * @param string $id         The mailbox ID.
     * @param boolean $editable  Is this an editable (i.e. not built-in)
     *                           filter query?
     */
    public function isFilter($id, $editable = false)
    {
        return (isset($this->_search['filters'][$this->_strip($id)]) &&
                (!$editable || $this[$id]->canEdit));
    }

    /**
     * Converts a filter to a search query and stores it in the local
     * session.
     *
     * @param string $id     The mailbox ID of the filter.
     * @param array $mboxes  The list of mailboxes to apply the filter on.
     * @param string $mid    Use as the mailbox ID.
     *
     * @return IMP_Search_Query  The created query object.
     * @throws InvalidArgumentException
     */
    public function applyFilter($id, array $mboxes, $mid = null)
    {
        if (!$this->isFilter($id)) {
            throw new InvalidArgumentException('Invalid filter ID given.');
        }

        if (!is_null($mid)) {
            $mid = $this->_strip($mid);
        }

        $q_ob = $this[$id]->toQuery($mboxes, $mid);
        $this->_search['query'][$q_ob->id] = $q_ob;
        $this->changed = true;

        return $q_ob;
    }

    /**
     * Saves the list of virtual folders for the current user.
     *
     * @param array $vfolders  The virtual folder list.
     */
    public function setVFolders($vfolders)
    {
        $GLOBALS['prefs']->setValue('vfolder', serialize(array_values($vfolders)));
    }

    /**
     * Loads the list of virtual folders for the current user.
     */
    protected function _getVFolders()
    {
        $vf = array();

        /* Build list of default virtual folders. */
        $di = new DirectoryIterator(IMP_BASE . '/lib/Search/Vfolder');
        $disable = array('IMP_Search_Vfolder_Vtrash');

        foreach ($di as $val) {
            if ($val->isFile()) {
                $cname = 'IMP_Search_Vfolder_' . $val->getBasename('.php');
                if (($cname != 'IMP_Search_Vfolder_Builtin') &&
                    class_exists($cname)) {
                    $vfolder = new $cname(array(
                        'disable' => in_array($cname, $disable)
                    ));
                    $vf[$vfolder->id] = $vfolder;
                }
            }
        }

        if ($pref_vf = $GLOBALS['prefs']->getValue('vfolder')) {
            $pref_vf = @unserialize($pref_vf, array('allowed_classes' => array(
                'IMP_Search_Vfolder_Vinbox',
                'IMP_Search_Vfolder_Vtrash',
            )));
            if (is_array($pref_vf)) {
                foreach ($pref_vf as $val) {
                    if ($val instanceof IMP_Search_Vfolder) {
                        $vf[$val->id] = $val;
                    }
                }
            }
        }

        $this->_search['vfolders'] = $vf;
        $this->changed = true;
    }

    /**
     * Is a mailbox a virtual folder?
     *
     * @param string $id         The mailbox ID.
     * @param boolean $editable  Is this an editable (i.e. not built-in)
     *                           virtual folder?
     *
     * @return boolean  Whether the mailbox ID is a virtual folder.
     */
    public function isVFolder($id, $editable = false)
    {
        return (isset($this->_search['vfolders'][$this->_strip($id)]) &&
                (!$editable || $this[$id]->canEdit));
    }

    /**
     * Determines whether a mailbox ID is the Virtual Trash Folder.
     *
     * @param string $id  The mailbox id.
     *
     * @return boolean  True if the ID is the Virtual Trash folder.
     */
    public function isVTrash($id)
    {
        return (($this->isVFolder($id)) &&
            ($this[$id] instanceof IMP_Search_Vfolder_Vtrash));
    }

    /**
     * Determines whether a mailbox ID is the Virtual INBOX Folder.
     *
     * @param string $id  The mailbox id.
     *
     * @return boolean  True if the ID is the Virtual INBOX folder.
     */
    public function isVinbox($id)
    {
        return (($this->isVFolder($id)) &&
            ($this[$id] instanceof IMP_Search_Vfolder_Vinbox));
    }

    /**
     * Is a mailbox a search query?
     *
     * @param string $id         The mailbox ID.
     * @param boolean $editable  Is this an editable (i.e. not built-in)
     *                           search query?
     *
     * @return boolean  True if a search query.
     */
    public function isQuery($id, $editable = false)
    {
        return (isset($this->_search['query'][$this->_strip($id)]) &&
                (!$editable || !$this->isSystemQuery($id)));
    }

    /**
     * Is a mailbox a system (built-in) search query?
     *
     * @param string $id  The mailbox ID.
     *
     * @return boolean  True if a system search query.
     */
    public function isSystemQuery($id)
    {
        return (isset($this->_search['query'][$this->_strip($id)]) &&
                in_array($this[$id]->id, array(self::FILTERSEARCH, self::QUICKSEARCH)));
    }

    /**
     * Returns a link to edit a given search query.
     *
     * @param string $id  The search query id.
     *
     * @return Horde_Url  The URL to the search page.
     */
    public function editUrl($id)
    {
        $mbox = IMP_Mailbox::get($this->createSearchId($id));

        return IMP_Dynamic_Mailbox::url()->setAnchor(
            'search:' . json_encode(array(
                'edit_query' => 1,
                'mailbox' => $mbox->form_to
            ))
        );
    }

    /**
     * Is the given mailbox a search mailbox?
     *
     * @param string $id  The mailbox name.
     *
     * @return boolean  Whether the given mailbox name is a search mailbox.
     */
    public function isSearchMbox($id)
    {
        return (strpos($id, self::MBOX_PREFIX) === 0);
    }

    /**
     * Strip the identifying label from a mailbox ID.
     *
     * @param string $id  The mailbox query ID.
     *
     * @return string  The virtual folder ID, with any IMP specific
     *                 identifying information stripped off.
     */
    protected function _strip($id)
    {
        return $this->isSearchMbox($id)
            ? substr($id, strlen(self::MBOX_PREFIX))
            : strval($id);
    }

    /**
     * Create the canonical search ID for a given search query.
     *
     * @param string $id  The mailbox query ID.
     *
     * @return string  The canonical search query ID.
     */
    public function createSearchId($id)
    {
        return self::MBOX_PREFIX . $this->_strip($id);
    }

    /* ArrayAccess methods. */

    public function offsetExists($offset)
    {
        $id = $this->_strip($offset);

        foreach (array_keys($this->_search) as $key) {
            if (isset($this->_search[$key][$id])) {
                return true;
            }
        }

        return false;
    }

    public function offsetGet($offset)
    {
        $id = $this->_strip($offset);

        foreach (array_keys($this->_search) as $key) {
            if (isset($this->_search[$key][$id])) {
                return $this->_search[$key][$id];
            }
        }

        return null;
    }

    /**
     * Alter the current IMAP search query.
     *
     * @param string $offset           The search query id.
     * @param IMP_Search_Query $value  The query object.
     *
     * @throws InvalidArgumentException
     */
    public function offsetSet($offset, $value)
    {
        if (!($value instanceof IMP_Search_Query)) {
            throw new InvalidArgumentException('$value must be a query object.');
        }

        $id = $this->_strip($offset);

        foreach (array_keys($this->_search) as $key) {
            if (isset($this->_search[$key][$id])) {
                $this->_search[$key][$id] = $value;
                $this->changed = true;

                if ($key == 'vfolders') {
                    $this->setVFolders($this->_search['vfolders']);

                    $ftree = $GLOBALS['injector']->getInstance('IMP_Ftree');
                    $ftree->delete($value);
                    $ftree->insert($value);
                }
                return;
            }
        }

        throw new InvalidArgumentException('Creating search queries by array index is not supported. Use createQuery() instead.');
    }

    /**
     * Deletes an IMAP search query.
     *
     * @param string $offset  The search query id.
     */
    public function offsetUnset($offset)
    {
        $id = $this->_strip($offset);

        foreach (array_keys($this->_search) as $val) {
            if (isset($this->_search[$val][$id])) {
                $value = $this->_search[$val][$id];
                unset($this->_search[$val][$id]);
                $this->changed = true;

                if ($val == 'vfolders') {
                    $this->setVFolders($this->_search['vfolders']);
                    $GLOBALS['injector']->getInstance('IMP_Ftree')->delete($value);
                }
                break;
            }
        }
    }

    /* IteratorAggregate method. */

    /**
     */
    public function getIterator()
    {
        $iterator = new AppendIterator();
        foreach ($this->_search as $val) {
            $iterator->append(new ArrayIterator($val));
        }
        return $iterator;
    }

    /* Serializable methods. */

    /**
     * Serialize.
     *
     * @return string  Serialized representation of this object.
     */
    public function serialize()
    {
        return $GLOBALS['injector']->getInstance('Horde_Pack')->pack(
            $this->_search,
            array(
                'compression' => false,
                'phpob' => true
            )
        );
    }

    /**
     * Unserialize.
     *
     * @param string $data  Serialized data.
     *
     * @throws Exception
     */
    public function unserialize($data)
    {
        $this->_search = $GLOBALS['injector']->getInstance('Horde_Pack')->unpack($data);
    }

}
