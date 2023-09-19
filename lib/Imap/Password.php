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
 * Dynamically generate the password needed for the mail server connection.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2013-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Imap_Password
implements Horde_Imap_Client_Base_Password, Serializable
{
    /* Password key. */
    const PASSWORD_KEY = 'imap_ob_pass';

    /**
     * Object storage ID.
     *
     * @var string
     */
    private $_id;

    /**
     * Mail server password.
     *
     * @var string
     */
    private $_password;

    /**
     * Constructor.
     *
     * @param string $password  The mail server password.
     */
    public function __construct($password)
    {
        $this->_password = $password;
    }

    /* Horde_Imap_Client_Base_Password methods. */

    /**
     */
    public function getPassword()
    {
        return $this->_password;
    }

    /* Serializable methods. */

    /**
     */
    public function serialize()
    {
        return array_shift($this->__serialize());
    }
    public function __serialize(): array 
    {
        global $session;

        if (!isset($this->_id)) {
            $this->_id = strval(new Horde_Support_Randomid());
        }

        $session->set('imp', self::PASSWORD_KEY . '/' . $this->_id, $this->_password, $session::ENCRYPT);

        return
        [
            $this->_id
        ];        
    }
    /**
     * @throws RuntimeException
     */
    public function unserialize($data)
    {
        $this->__unserialize([$data]);
    }
    public function __unserialize(array $data): void 
    {
        $this->_id = $data[0];

        $password = $GLOBALS['session']->get(
            'imp',
            self::PASSWORD_KEY . '/' . $this->_id
        );
        if (!strlen($password)) {
            /* Without a password, this object is invalid. */
            throw new RuntimeException(
                'Invalid object since password is empty.'
            );
        }
        $this->_password = $password;

    }
}
