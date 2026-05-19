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
 * This class manages the attrib_text preference.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Prefs_AttribText
{
    /**
     * Attribution text.
     *
     * @var string
     */
    protected $_text;

    /**
     * Constructor.
     *
     * @param string|Horde_Mail_Rfc822_Object $from  The email address of the
     *                                               original sender.
     * @param Horde_Mime_Headers $h                  The headers object for
     *                                               the message.
     * @param string $attrib                         Use this for the
     *                                               attribution config
     *                                               instead of the default
     *                                               prefs version.
     */
    public function __construct($from, Horde_Mime_Headers $h, $attrib = null)
    {
        global $prefs;

        $this->_text = preg_replace_callback(
            '/\%./',
            function ($matches) use ($from, $h) {
                switch ($matches[0]) {
                    case '%n': /* New line. */
                        return "\n";

                    case '%%': /* Percent character. */
                        return '%';

                    case '%f': /* Name and email address of original sender. */
                        if ($from) {
                            $from = new Horde_Mail_Rfc822_Address($from);
                            return $from->writeAddress(['noquote' => true]);
                        }
                        return _('Unknown Sender');

                    case '%a': /* Senders email address(es). */
                    case '%p': /* Senders name(s). */
                        $out = [];
                        foreach (IMP::parseAddressList($from) as $addr) {
                            if ($matches[0] == '%a') {
                                if (!is_null($addr->mailbox)) {
                                    $out[] = $addr->bare_address;
                                }
                            } else {
                                $out[] = $addr->label;
                            }
                        }
                        return count($out)
                            ? implode(', ', $out)
                            : _('Unknown Sender');

                    case '%r': /* RFC 822 date and time. */
                        return $h['Date'];

                    case '%d': /* Date as ddd, dd mmm yyyy. */
                        return (new IntlDateFormatter(
                            $GLOBALS['language'],
                            IntlDateFormatter::NONE,
                            IntlDateFormatter::NONE,
                            null,
                            null,
                            'EEE, dd MMM yyyy'
                        ))->format(strtotime($h['Date']));

                    case '%c': /* Date and time in locale's default. */
                        return (new IntlDateFormatter(
                            $GLOBALS['language'],
                            IntlDateFormatter::SHORT,
                            IntlDateFormatter::MEDIUM
                        ))->format(strtotime($h['Date']));

                    case '%x': /* Date in locale's default. */
                        return (new IntlDateFormatter(
                            $GLOBALS['language'],
                            IntlDateFormatter::SHORT,
                            IntlDateFormatter::NONE
                        ))->format(strtotime($h['Date']));

                    case '%m': /* Message-ID. */
                        return strval($h['Message-Id']);

                    case '%s': /* Message subject. */
                        return strlen($subject = $h['Subject'])
                            ? $subject
                            : _('[No Subject]');

                    default:
                        return '';
                }
            },
            is_null($attrib) ? $prefs->getValue('attrib_text') : $attrib
        );
    }

    /**
     */
    public function __toString()
    {
        return $this->_text;
    }

}
