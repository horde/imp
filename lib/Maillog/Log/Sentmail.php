<?php
/**
 * Copyright 2015-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2015-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Log entry that references a sent-mail action.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2015-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 *
 * @property-read string $msg_id  Message-ID of the message sent.
 */
abstract class IMP_Maillog_Log_Sentmail
extends IMP_Maillog_Log_Base
{
    /**
     * Sent-mail folder.
     *
     * @var string
     */
    protected $_folder;

    /**
     * Message ID.
     *
     * @var string
     */
    protected $_msgId;

    /**
     * Message ID header label.
     *
     * @var string
     */
    protected $_msgidHeader = 'Message-ID';

    /**
     * Constructor.
     *
     * @param array $params  Parameters:
     *   - folder: (IMP_Mailbox|string) Potential sent-mail folder.
     *   - msgid: (string) Message ID of the message sent.
     */
    public function __construct(array $params = array())
    {
        if (isset($params['folder'])) {
            $this->_folder = strval($params['folder']);
        }
        if (isset($params['msgid'])) {
            $this->_msgId = strval($params['msgid']);
        }
        parent::__construct($params);
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
        case 'folder':
            return $this->_folder;
        case 'msg_id':
            return $this->_msgId;
        }

        return parent::__get($name);
    }

    /**
     * Add entry specific data to the backend storage.
     *
     * @return array  An array of key -> value pairs to add.
     */
    public function addData()
    {
        return array_merge(parent::addData(), array_filter(array(
            'folder' => $this->folder,
            'msgid' => $this->msg_id
        )));
    }

    /**
     * Return the mailboxes that can be searched to find the sent message.
     *
     * @return array  Array of mailboxes to search in order of priority.
     */
    public function searchMailboxes()
    {
        $special = IMP_Mailbox::getSpecialMailboxes();

        /* Check for sent-mail mailbox(es) first. */
        $out = array();
        if ($this->folder) {
            $out[] = new IMP_Mailbox($this->folder);
        }
        $out = array_unique(array_merge($out, $special[IMP_Mailbox::SPECIAL_SENT]));

        /* Add trash mailbox as backup. */
        if (!empty($special[IMP_Mailbox::SPECIAL_TRASH]) &&
            !$special[IMP_Mailbox::SPECIAL_TRASH]->vtrash) {
            $out[] = $special[IMP_Mailbox::SPECIAL_TRASH];
        }

        return $out;
    }

    /**
     * Return the search query to use to find the sent message.
     *
     * @return Horde_Imap_Client_Search_Query  The query object.
     */
    public function searchQuery()
    {
        $query = new Horde_Imap_Client_Search_Query();
        $query->headerText($this->_msgidHeader, $this->msg_id);
        return $query;
    }

}
