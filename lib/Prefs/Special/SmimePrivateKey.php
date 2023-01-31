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
 * Special prefs handling for the 'smimeprivatekey' preference.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2012-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Prefs_Special_SmimePrivateKey implements Horde_Core_Prefs_Ui_Special
{
    /**
     * View variable to share accross functions in this class, contains the ui?
     */
    private $view = null;

    /**
     * Identities variable: set to true if the users loads the prefs page to set identities
     */
    private $identities = false;

    /**
     * Smime url: generates the URL needed for the links to the SMIME keys
     */
    private $smime_url;

    /**
     * Smime prefs url: generate the url for the prefs for smime
     */
    private $smime_prefs_url;

    /**
     * Smime: class that holds the variable that interact with the database
     */
    private $smime;

    /**
     * Identity: class that holds methods to get information about the identities saved in the prefs table
     */
    private $identity;

    /**
     */
    public function init(Horde_Core_Prefs_Ui $ui)
    {
        global $injector;

        /* Loading Smime bas url in order to set links to it */
        $this->smime_url = IMP_Basic_Smime::url();

        /* Page base url */
        $this->smime_prefs_url = Horde::url($GLOBALS['registry']->getServiceLink('prefs', 'imp'), true)->add('group', 'smime');

        /* Loading the IMP Smime class which hods all the methods that a.o. interact wiht the DB */
        $this->smime = $injector->getInstance('IMP_Smime');

        /* Get Identity Class Object */
        $this->identity =  $injector->getInstance('IMP_Identity');
    }

    private function checkIdentityPageIsUsed(Horde_Core_Prefs_Ui $ui)
    {
        if ($ui->vars->group === 'identities') {
            $this->identities = true;
            return true;
        }
        return false;
    }

    private function setUploadScripts($ui, $identityID = null)
    {
        global $browser, $page_output, $session;

        $view = $this->view;
        $identities = $this->identities;
        $smime_url = $this->smime_url;

        if ($browser->allowFileUploads()) {
            $view->import = true;
            $page_output->addInlineScript([
                'if ($("import_smime_personal") != undefined) $("import_smime_personal").observe("click", function(e) { ' . Horde::popupJs($smime_url, ['params' => ['actionID' => 'import_personal_certs', 'reload' => base64_encode($ui->selfUrl()->setRaw(true))], 'height' => 450, 'width' => 750, 'urlencode' => true]) . '; e.stop(); })',
                'if ($("import_extra_smime_personal") != undefined) $("import_extra_smime_personal").observe("click", function(e) { ' . Horde::popupJs($smime_url, ['params' => ['actionID' => 'import_extra_personal_certs', 'reload' => base64_encode($ui->selfUrl()->setRaw(true))], 'height' => 450, 'width' => 750, 'urlencode' => true]) . '; e.stop(); })',
            ], true);
        }

        if ($identities) {
            $page_output->addScriptFile('prefs/switchencryptionoptions.js');
        }
    }

    /**
     * Function to list all extra keys
     */
    private function listExtraKeys($identityID = 0)
    {
        try {
            $extra_private_keys = $this->smime->listAllKeys($prefName = 'smime_private_key', $identityID); // TODO: what about singkeys?
        } catch (Horde_Exception $e) {
            $extra_private_keys = [];
        }

        return $extra_private_keys;
    }

    /**
     * Displays function of Horde_Core_Prefs_Ui, called after init()
     */
    public function display(Horde_Core_Prefs_Ui $ui, $identityID =0)
    {
        global $injector, $prefs, $page_output, $vars;

        $this->checkIdentityPageIsUsed($ui);

        $identity = $this->identity;
        $identities = $this->identities;
        $smime_url = $this->smime_url;
        $smime = $this->smime;

        /* Adding js to page output */
        $page_output->addScriptPackage('IMP_Script_Package_Imp');

        /* Get the current default Idenity ID to load the keys that belong to it */
        $defaultIdentity = $identity->getDefault();

        /* Adding css to page output */
        $p_css = new Horde_Themes_Element('prefs.css');
        $page_output->addStylesheet($p_css->fs, $p_css->uri);

        /* an instance of IMP_smime to be able to list all keys, their ids and aliases from the DB */
        try {
            $extra_private_keys = $smime->listAllKeys($prefName = 'smime_private_key', $defaultIdentity); // TODO: what about singkeys?
        } catch (Horde_Exception $e) {
            $extra_private_keys = [];
        }

        /* Loading View Template and Help Template */
        $this->view = $view = new Horde_View([
            'templatePath' => IMP_TEMPLATES . '/prefs',
        ]);
        $view->addHelper('Horde_Core_View_Helper_Help');

        /* Set the result of the identity check to the view */
        $view->identities = $identities;

        /* Loading Connection Status to View */
        if (!Horde::isConnectionSecure()) {
            $view->notsecure = true;
            return $view->render('smimeprivatekey');
        }

        /* Loading Keys that are set as Personal Certificate
         (the certificates that are actually used) */

        // Check if this concerns the keys of an identity.
        // Identities are used to reply to mails with a seemingly different account.
        // See: Preferences > Mail > Personal Information
        if (!$identities) {  
            $view->has_key = $smime->getPersonalPrivateKey(0, $defaultIdentity) &&
                $smime->getPersonalPublicKey(0, $defaultIdentity); // check if both private and public keys can be fetched (returns a boolean)
                $smime->getPersonalPrivateKey(1, $defaultIdentity) &&
                $smime->getPersonalPublicKey(1, $defaultIdentity);
        } else {
            // Ask user to go the smime page to set the keys for each identity
            $view->relink =  Horde::link($this->smime_prefs_url)
            . _('Change SMIME preferences here') . '</a>';
        }


        /* Addding to view: Browser Importoptions for uploading Certificates */
        $this->setUploadScripts($ui);


        /* Loading private keys from the Database that are not used as Personal Certificates */
        if (!empty($extra_private_keys)) {
            // adding base url links to each private key: so one can view the keys
            $pk_list = [];
            $countnumber = 0;
            foreach ($extra_private_keys as $val) {
                $privatelink = $smime_url->copy()->add(['actionID' => 'view_extra_private_keys', 'pkID' => $val['private_key_id']]);
                $publiclink = $smime_url->copy()->add(['actionID' => 'view_extra_public_keys', 'pkID' => $val['private_key_id']]);
                $publicInfoLink = $smime_url->copy()->add(['actionID' => 'view_extra_public_info', 'pkID' => $val['private_key_id']]);
                $title = 'View Extra Private Keys';
                $pk_list[$countnumber]['publiclink'] = Horde::link($publiclink, $title, null, 'view_key');
                $pk_list[$countnumber]['publicinfolink'] = Horde::link($publicInfoLink, $title, null, 'info_key');
                $pk_list[$countnumber]['privatelink'] = Horde::link($privatelink, $title, null, 'view_key');
                $pk_list[$countnumber]['id'] = $val['private_key_id'];
                $pk_list[$countnumber]['alias'] = $val['alias'];
                $countnumber ++;
            }

            // Adding extra private keys to view
            $view->{'viewprivateextras'} = $pk_list;
        }

        /* If no key was found, return the current view and discontinue rest of the logic from here on */
        if (!$view->has_key) {
            return $view->render('smimeprivatekey');
        }

        /* adding primary and secondary personal keys */
        foreach (['' => false, '_sign' => true] as $suffix => $secondary) {
            // If no secondary Ceritificates or signkeys are found: skip this loop
            if ($secondary && !$view->has_sign_key) {
                continue;
            }

            $pubkey = $smime->getPersonalPublicKey($secondary, $defaultIdentity);
            $cert = $smime->parseCert($pubkey);

            // Checking for validity date if set
            if (!empty($cert['validity']['notafter'])) {
                $expired = new Horde_Date($cert['validity']['notafter']);
                if ($expired->before(time())) {
                    $view->{'expiredate' . $suffix} = $expired->strftime(
                        $prefs->getValue('date_format')
                    );
                    $view->{'expiretime' . $suffix} = $expired->strftime(
                        $prefs->getValue('time_format')
                    );
                }
            }

            // TODO: default identity should be gotten from the identity field in the prefs table always so switching becomes possible
            $view->{'viewpublic' . $suffix} = $smime_url->copy()
                ->add('actionID', 'view_personal_public' . $suffix . '_key')
                ->link([
                    'title' => $secondary
                        ? _('View Secondary Personal Public Certificate')
                        : _('View Personal Public Certificate'),
                    'target' => 'view_key',
                ])
                . _('View') . '</a>';

            $view->{'infopublic' . $suffix} = $smime_url->copy()
                ->add('actionID', 'info_personal_public' . $suffix . '_key')
                ->link([
                    'title' => _('Information on Personal Public Certificate'),
                    'target' => 'info_key',
                ])
                . _('Details') . '</a>';

            $view->{'privatekeyexits'} = $smime->getSetPrivateKeyId(0, $defaultIdentity); // check if private key exists and return its id value if so
            $view->{'signkeyexits'} = $smime->getSetPrivateKeyId(1, $defaultIdentity); // Note: self::KEY_SECONDARY = 1 in Smime.php...  This checks if a sign ey exists and returns the id
            $view->{'aliasexists'} = $smime->getAlias($view->privatekeyexits, $defaultIdentity); // gets the alias of the key by ID
            $view->{'signaliasexists'} = $smime->getAlias($view->signkeyexits, $defaultIdentity); // gets the alias of the key by ID

            // set alias
            if ($secondary === true) {
                $imple2 = $injector->getInstance('Horde_Core_Factory_Imple')->create(
                    'IMP_Ajax_Imple_AliasDialog',
                    [
                        'params' => [
                            'reload' => $ui->selfUrl()->setRaw(true),
                            'secondary' => intval($secondary),
                        ],
                        'keyid' => $view->signkeyexits,
                    ]
                );
            } else {
                $imple2 = $injector->getInstance('Horde_Core_Factory_Imple')->create(
                    'IMP_Ajax_Imple_AliasDialog',
                    [
                        'params' => [
                            'reload' => $ui->selfUrl()->setRaw(true),
                            'secondary' => intval($secondary),
                        ],
                        'keyid' => $view->privatekeyexits,
                    ]
                );
            }

            $view->{'alias' . $suffix} = Horde::link(
                '#',
                _('Enter Alias'),
                null,
                null,
                null,
                null,
                null,
                ['id' => $imple2->getDomId()]
            ) . _('Enter Alias')  . '</a>';

            if ($smime->getPassphrase($secondary)) {
                $view->{'passphrase' . $suffix} = $ui->selfUrl([
                    'special' => true,
                    'token' => true,
                ])
                ->add('unset_smime' . $suffix . '_passphrase', 1)
                ->link([
                    'title' => _('Unload Passphrase'),
                ])
                . _('Unload Passphrase') . '</a>';
            } else {
                $imple = $injector->getInstance('Horde_Core_Factory_Imple')
                    ->create(
                        'IMP_Ajax_Imple_PassphraseDialog',
                        [
                            'params' => [
                                'reload' => $ui->selfUrl()->setRaw(true),
                                'secondary' => intval($secondary),
                            ],
                            'type' => 'smimePersonal',
                        ]
                    );
                $view->{'passphrase' . $suffix} = Horde::link(
                    '#',
                    _('Enter Passphrase'),
                    null,
                    null,
                    null,
                    null,
                    null,
                    ['id' => $imple->getDomId()]
                ) . _('Enter Passphrase')  . '</a>';
            }

            // Adding to view: private key link
            $view->{'viewprivate' . $suffix} = $smime_url->copy()
                ->add('actionID', 'view_personal_private' . $suffix . '_key')
                ->link([
                    'title' => _('View Secondary Personal Private Key'),
                    'target' => 'view_key',
                ])
                . _('View') . '</a>';

            // Warning if the set personal key is about to be deleted
            $page_output->addInlineScript([
                'if ($("delete_smime_personal' . $suffix . '") != undefined) $("delete_smime_personal' . $suffix . '").observe("click", function(e) { if (!window.confirm(' . json_encode(_('Are you sure you want to delete your keypair? (Please click "Unset Personal Certificate" if you want to save the key in the keystore. Non-saved certificates will be removed permanently.)')) . ')) { e.stop(); } })',
                'if ($("delete_smime_extra' . $suffix . '") != undefined) $("delete_smime_extra' . $suffix . '").observe("click", function(e) { if (!window.confirm(' . json_encode(_('Are you sure you want to delete your keypair? You are trying to delete a keypair from the keystore. If you continue these certificates will be removed permanently. ')) . ')) { e.stop(); } })',
                'if ($("unset_smime_personal' . $suffix . '") != undefined) $("unset_smime_personal' . $suffix . '").observe("click", function(e) { if (!window.confirm(' . json_encode(_('Are you sure you want to unset your keypair? You will need to add another key to be able to send encrypted mails.')) . ')) { e.stop(); } })',
                'if ($("set_smime_personal' . $suffix . '") != undefined) $("set_smime_personal' . $suffix . '").observe("click", function(e) { if (!window.confirm(' . json_encode(_('Are you sure you want to set a new keypair? Currently set keypair will be moved to the database. New emails will be encrypted with the newly set keypair.')) . ')) { e.stop(); } })',
                'if ($("unset_smime_secondary' . $suffix . '") != undefined) $("unset_smime_secondary' . $suffix . '").observe("click", function(e) { if (!window.confirm(' . json_encode(_('Are you sure you want to unset your secondary keypair?')) . ')) { e.stop(); } })',
                'if ($("set_smime_secondary' . $suffix . '") != undefined) $("set_smime_secondary' . $suffix . '").observe("click", function(e) { if (!window.confirm(' . json_encode(_('Are you sure you want to set a singing key? Current key for singing will be moved to the database. New emails will be singing with the newly set singing key.')) . ')) { e.stop(); } })',
            ], true);
        }



        return $view->render('smimeprivatekey');
    }


    /**
     */
    public function update(Horde_Core_Prefs_Ui $ui)
    {
        global $injector, $notification;

        $identityID = $this->identity->getDefault();

        if (isset($ui->vars->delete_smime_personal) || // delete personal certificates
            isset($ui->vars->delete_smime_personal_sign)) {
            $injector->getInstance('IMP_Smime')->deletePersonalKeys( // for deletion this needs to get the identity id...
                $ui->vars->delete_smime_personal_sign,
                $identityID
            );
            $notification->push(
                isset($ui->vars->delete_smime_personal_sign)
                    ? _('Secondary personal S/MIME keys deleted successfully.')
                    : _('Personal S/MIME keys deleted successfully.'),
                'horde.success'
            );
        } elseif (isset($ui->vars->delete_smime_extra)) { // delete extra certificates
            $injector->getInstance('IMP_Smime')->deleteExtraKey(
                $ui->vars->delete_smime_extra,
                $identityID
            );
            $notification->push(
                isset($ui->vars->delete_smime_extra_secondary) // TODO: fix deletion of additional extra secondary keys?
                    ? _('Secondary extra S/MIME keys deleted successfully.')
                    : _('Extra S/MIME keys deleted successfully.'),
                'horde.success'
            );
        } elseif (isset($ui->vars->unset_smime_passphrase) || // change passphrase
                  isset($ui->vars->unset_smime_sign_passphrase)) {
            $injector->getInstance('IMP_Smime')->unsetPassphrase(
                $ui->vars->unset_smime_sign_passphrase
            );
            $notification->push(
                _('S/MIME passphrase successfully unloaded.'),
                'horde.success'
            );
        } elseif (isset($ui->vars->unset_smime_personal)) { // unsetting personal certificate and transfering it to the db
            $injector->getInstance('IMP_Smime')->unsetSmimePersonal(0, false, $identityID);
        } elseif (isset($ui->vars->unset_smime_secondary)) { // unsetting secondary certificate and transfering it to the db
            $injector->getInstance('IMP_Smime')->unsetSmimeSecondary(1, $identityID);
        } elseif (isset($ui->vars->set_smime_personal)) { // setting personal certificate... first have to unset?
            // TODO: Problem: there is a problem here, when clicking next to the number, the first id gets selected!
            $injector->getInstance('IMP_Smime')->setSmimePersonal(
                $ui->vars->set_smime_personal,
                0,
                $identityID
            );
            $notification->push(
                _('S/MIME Certificate set and successfully transfered previous certificate to extra keys.'),
                'horde.success'
            );
        } elseif (isset($ui->vars->set_smime_secondary)) { // setting secondary certificate
            $injector->getInstance('IMP_Smime')->setSmimeSecondary(
                $ui->vars->set_smime_secondary,
                $identityID
            );
            $notification->push(
                _('S/MIME Singing Certificate set and successfully transfered previous signing certificate to extra keys.'),
                'horde.success'
            );
        }

        return false;
    }
}
