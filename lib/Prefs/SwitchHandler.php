<?php
/**
 * Passes Public Keys to the JS frontend concerning Identityes and Preferencess
 *
 * @author    Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

use IMP_Prefs_Identity;

class IMP_Prefs_SwitchHandler
{

    /**
     * gets the keys form the address book for the prefs identity frontend
     * 
     * @param integer $identityID: the identity from where the keys should be gotten
     * 
     * @return array returns an array containing links to the keys
     */
    public function getPublicKeysForPrefsIdentities($identity, $identityID){
        
        $email = $identity->getEmail();
        $name = $identity->getName($identityID);

        $smime_url = Horde::url('../imp/basic.php')->add('page', 'smime');

        try {
            $linksToKey = [
                'view' => Horde::link($smime_url->copy()->add(['actionID' => 'view_public_key', 'email' => $email]), sprintf(_('View %s Public Key'), $name), null, 'view_key')."key</a>",
                'info' => Horde::link($smime_url->copy()->add(['actionID' => 'info_public_key', 'email' => $email]), sprintf(_('View %s Public Info'), $name), null, 'info_key')."info</a>",
                ];
        } catch (\Throwable $th) {
            throw $th;
        }


        return $linksToKey;

    }
}