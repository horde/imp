<?php

use Horde\Injector\Injector;

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
 * A Horde_Injector based factory for the IMP_Contacts object.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2014-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Factory_Contacts extends Horde_Core_Factory_Injector implements Horde_Shutdown_Task
{
    public const SESS_KEY = 'contacts';

    /**
     * @var IMP_Contacts
     */
    private $_instance;

    /**
     * Return the IMP_Contacts instance.
     *
     * @return IMP_Contacts  The singleton instance.
     */
    public function create(Horde_Injector|Injector $injector)
    {
        try {
            $this->_instance = $GLOBALS['session']->get('imp', self::SESS_KEY);
        } catch (Throwable $e) {
            Horde::log('Could not unserialize stored IMP_Contacts object.', 'DEBUG');
        }

        /* HordeSession::getScoped returns the raw bytes when unpacking
         * fails, so a poisoned session slot surfaces here as a string (or
         * any other non-IMP_Contacts value) rather than as a thrown
         * exception. Treat anything that isn't an IMP_Contacts as missing
         * and rebuild from scratch. */
        if (!($this->_instance instanceof IMP_Contacts)) {
            $this->_instance = new IMP_Contacts();
        }

        Horde_Shutdown::add($this);

        return $this->_instance;
    }

    /**
     * Store serialized version of object in the current session.
     */
    public function shutdown()
    {
        /* Only need to store the object if the object has changed. The
         * instanceof guard protects against shutdown firing without a
         * prior create() call. */
        if ($this->_instance instanceof IMP_Contacts
            && $this->_instance->changed) {
            $GLOBALS['session']->set('imp', self::SESS_KEY, $this->_instance);
        }
    }

}
