<?php
/**
 * Copyright 2012-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * Defines AJAX actions used in the IMP alias dialog.
 *
 * @author    Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Ajax_Application_Handler_Alias extends Horde_Core_Ajax_Application_Handler
{
    /**
     * AJAX action: Check passphrase.
     *
     * Variables required in form input:
     *   - dialog_input: (string) Input from the dialog screen.
     *   - reload: (mixed) If set, reloads page instead of returning data.
     *   - symmetricid: (string) The symmetric ID to process.
     *   - type: (string) The passphrase type.
     *
     * @return boolean  True on success.
     */
    public function checkAlias()
    {
        global $injector;

        $alias = $this->vars->dialog_input;
        $keyid = $this->vars->keyid;

        $identity = $injector->getInstance('IMP_Identity');
        $identityID = $identity->getDefault();

        $result = new IMP_Prefs_Special_SmimeAliasHandler();
        $result = $result->handle($keyid, $alias, $identityID);

        return ($result && $this->vars->reload)
            ? new Horde_Core_Ajax_Response_HordeCore_Reload($this->vars->reload)
            : $result;
        
    }
}
