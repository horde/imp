<?php

/**
 * Special prefs handling to give information on which identity is currenlty set. This is for the SMIME prefs section.
 *
 * @author    Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Prefs_Special_SmimeIdentities implements Horde_Core_Prefs_Ui_Special
{
    /**
     */
    public function init(Horde_Core_Prefs_Ui $ui)
    {
    }

    /**
     */
    public function display(Horde_Core_Prefs_Ui $ui)
    {
        global $injector, $page_output;

        $view = new Horde_View([
            'templatePath' => IMP_TEMPLATES . '/prefs',
        ]);
        $view->addHelper('Horde_Core_View_Helper_Help');
        $view->addHelper('Text');

        $identity = $injector->getInstance('IMP_Identity');
        $identityID = $identity->getDefault();
        $view->defaultIdentity = $identity->getFullname($identityID);
        $view->defaultAdres = $identity->getEmail();
        $view->linkMailIdentity = Horde::url($GLOBALS['registry']->getServiceLink('prefs', 'imp'), true)->add('group', 'identities');

        return $view->render('smimeidentities');
    }

    /**
     */
    public function update(Horde_Core_Prefs_Ui $ui)
    {
        // this can eventually be updated to allow the user to switch identities within the smime prefs section. Could be more convenient.
        return false;
    }
}
