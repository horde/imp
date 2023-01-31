<?php

/**
 * Handler that passes Alias options to the SmimePrivateKey Class
 *
 * @author    Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Prefs_Special_SmimeAliasHandler
{
    public function handle($keyid, $alias, $identityID){

        global $injector, $notification;

        $result = false;
        
        if (empty($keyid) || empty($alias)) {
            $notification->push(_('No alias entered.'), 'horde.error');
            return $result;
        }

        try {
            $injector->getInstance('IMP_Smime')->updateAlias($keyid, $alias, $identityID);
            $result = true;
            $notification->push(_('Alias has been set.'), 'horde.success');
        } catch (Horde_Exception $e) {
            $notification->push($e, 'horde.error');
        }

        return ($result);
    }
}
