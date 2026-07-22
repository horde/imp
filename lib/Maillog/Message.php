<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Object representing a message to be logged.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 *
 * @property-read IMP_Indices $indices  Indices object.
 * @property-read string $msgid  Message-ID.
 */
class IMP_Maillog_Message
{
    /**
     * Index of the message.
     *
     * @var IMP_Indices
     */
    protected $_indices = null;

    /**
     * Message-ID.
     *
     * @var string
     */
    protected $_msgid = null;

    /**
     * Constructor.
     *
     * @param mixed $data  See add().
     */
    public function __construct($data)
    {
        $this->add($data);
    }

    /**
     * Populate the message payload.
     *
     * Accepts either an {@see IMP_Indices} (msgid resolved lazily on
     * first read via IMAP fetch) or an already-known Message-ID
     * string. Empty/false input is discarded so a getter that finds
     * neither payload can return an empty string cleanly rather than
     * fault on a null indices reference. See imp#96 —
     * Compose::sendRedirectMessage constructs one of these from a
     * `reset()` on a possibly-empty ids array, which yields `false`.
     */
    public function add($data)
    {
        if ($data instanceof IMP_Indices) {
            $this->_indices = $data;
        } elseif (($stringified = strval($data)) !== '') {
            $this->_msgid = $stringified;
        }
        /* else: caller handed us an empty/false value. Neither
         * $_indices nor $_msgid is set; the msgid getter returns ''
         * and downstream Storage/History::_getUniqueHistoryId's
         * empty-string guard turns it into a clean
         * RuntimeException. */
    }

    /**
     */
    public function __toString()
    {
        return $this->msgid;
    }

    /**
     */
    public function __get($name)
    {
        switch ($name) {
            case 'indices':
                return $this->_indices;

            case 'msgid':
                if (!$this->_msgid) {
                    /* Constructed without an IMP_Indices AND without a
                     * usable Message-ID. Callers that build a message
                     * with a false/empty/malformed id (see imp#96 —
                     * Compose::sendRedirectMessage on a message whose
                     * Message-ID header had no parseable id) would
                     * otherwise fault here with "Call to a member
                     * function getSingle() on null". Return an empty
                     * string; downstream storage layers (notably
                     * IMP_Maillog_Storage_History::_getUniqueHistoryId)
                     * already throw a clean RuntimeException on
                     * empty msgids, which is the intended failure
                     * mode. */
                    if ($this->_indices === null) {
                        return '';
                    }

                    [$mbox, $uid] = $this->_indices->getSingle();

                    $query = new Horde_Imap_Client_Fetch_Query();
                    $query->envelope();

                    $imp_imap = $mbox->imp_imap;

                    $ret = $imp_imap->fetch($mbox, $query, [
                        'ids' => $imp_imap->getIdsOb($uid),
                    ]);

                    $this->_msgid = ($ob = $ret[$uid])
                        ? $ob->getEnvelope()->message_id
                        : '';
                }

                return $this->_msgid;
        }
    }

}
