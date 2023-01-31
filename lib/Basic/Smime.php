<?php
/**
 * Copyright 2002-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */

/**
 * S/MIME display.
 *
 * @author    Mike Cochrane <mike@graftonhall.co.nz>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Basic_Smime extends IMP_Basic_Base
{
    /**
     * @var IMP_Smime
     */
    protected $_smime;

    /**
     * @var IMP_Identity
     */
    protected $_identity;

    /**
     */
    protected function _init()
    {
        global $injector, $notification;

        $this->_smime = $injector->getInstance('IMP_Smime');
        $this->_identity = $injector->getInstance('IMP_Identity');
        $identityID = $this->_identity->getDefault();

        /* Run through the action handlers */
        switch ($this->vars->actionID) {
            case 'import_public_key':
                $this->_importKeyDialog('public');
                break;

            case 'process_import_public_key':
                try {
                    $publicKey = $this->_getImportKey('upload_key', $this->vars->import_key);

                    /* Add the public key to the storage system. */
                    $this->_smime->addPublicKey($publicKey);
                    $notification->push(_('S/MIME public key successfully added.'), 'horde.success');
                    $this->_reloadWindow();
                } catch (Horde_Browser_Exception $e) {
                    $notification->push(_('No S/MIME public key imported.'), 'horde.error');
                } catch (Horde_Exception $e) {
                    $notification->push($e);
                }

                $this->vars->actionID = 'import_public_key';
                $this->_importKeyDialog('public');
                break;

            case 'view_public_key':
            case 'info_public_key':
                try {
                    $key = $this->_smime->getPublicKey($this->vars->email, $identityID); // here the method is never used to get the keys of an identity
                } catch (Horde_Exception $e) {
                    $key = $e->getMessage();
                }
                if ($this->vars->actionID == 'view_public_key') {
                    $this->_textWindowOutput('S/MIME Public Key', $key);
                }
                $this->_printCertInfo($key);
                break;

            case 'view_personal_public_key':
            case 'view_personal_public_sign_key':
                $this->_textWindowOutput(
                    'S/MIME Personal Public Key',
                    $this->_smime->getPersonalPublicKey(
                        $this->vars->actionID == 'view_personal_public_sign_key',
                        $identityID
                    )
                );
                break;

            case 'info_personal_public_key':
            case 'info_personal_public_sign_key':
                $this->_printCertInfo(
                    $this->_smime->getPersonalPublicKey(
                        $this->vars->actionID == 'info_personal_public_sign_key',
                        $identityID
                    )
                );
                break;

            case 'view_extra_public_info':
                $this->_printCertInfo(
                    $this->_smime->getExtraPublicKey(
                        $this->vars->pkID,
                        // if actionID euquals value, add it, otherwise load defaul value of function
                        $this->vars->actionID == 'view_personal_public_sign_info',
                        $identityID
                    )
                );
                break;

            case 'view_personal_private_key':
            case 'view_personal_private_sign_key':
                $this->_textWindowOutput(
                    'S/MIME Personal Private Key',
                    $this->_smime->getPersonalPrivateKey(
                        $this->vars->actionID == 'view_personal_private_sign_key',
                        $identityID
                    )
                );
                break;

            case 'view_extra_private_keys':
                $this->_textWindowOutput(
                    'S/MIME Extra Private Keys',
                    $this->_smime->getExtraPrivateKey(
                        $this->vars->pkID,
                        // if actionID euquals value, add it, otherwise load defaul value of function
                        $this->vars->actionID == 'view_personal_private_sign_key',
                        $identityID
                    )
                );
                break;

            case 'view_extra_public_keys':
                $this->_textWindowOutput(
                    'S/MIME Extra Private Keys',
                    $this->_smime->getExtraPublicKey(
                        $this->vars->pkID,
                        // if actionID euquals value, add it, otherwise load defaul value of function
                        $this->vars->actionID == 'view_personal_public_sign_key',
                        $identityID
                    )
                );
                break;

            case 'import_personal_certs':
                $this->_importKeyDialog('personal');
                break;

            case 'import_extra_personal_certs':
                $this->_importKeyDialog('extra');
                break;

            case 'import_extra_identity_certs':
                $this->_importKeyDialog('identity');
                break;

            case 'process_import_extra_identity_certs':
            case 'process_import_extra_personal_certs':
                $reload = false;
                $pkcs12_2nd = false;
                try {
                    // check if identity or personal certs are added
                    $identity = false;
                    $extra = false;
                    $identity_used =false;
                    if ($this->vars->actionID === 'process_import_extra_identity_certs') {
                        $identity=true;
                        $identityID = $this->_identity->getDefault();
                    } else {
                        $extra=true;
                    }
                    $pkcs12 = $this->_getImportKey('upload_key');
                    $this->_smime->addFromPKCS12($pkcs12, $this->vars->upload_key_pass, $this->vars->upload_key_pk_pass, null, $extra, $identityID, $identity_used);
                    // notifications on success or failure are in addFromPKCS12()
                    if ($pkcs12_2nd = $this->_getSecondaryKey()) {
                        // TODO: fix setup for secondary sign keys and such
                    }
                    $reload = true;
                } catch (Horde_Browser_Exception $e) {
                    if ($e->getCode() != UPLOAD_ERR_NO_FILE ||
                        !($pkcs12_2nd = $this->_getSecondaryKey())) {
                        $notification->push(_('Personal S/MIME certificates NOT imported.'), 'horde.error');
                    }
                } catch (Horde_Exception $e) {
                    $notification->push(_('Personal S/MIME certificates NOT imported: ') . $e->getMessage(), 'horde.error');
                }
                // TODO: fix setup for secondary sign keys and such
                if ($reload) {
                    $this->_reloadWindow();
                }

                // set correct actionID and importKeyDialog feature
                if ($identity) {
                    $this->vars->actionID = 'import_extra_identity_certs';
                    $this->_importKeyDialog('identity');
                } elseif ($extra) {
                    $this->vars->actionID = 'import_extra_personal_certs';
                    $this->_importKeyDialog('extra');
                }

                break;

            case 'process_import_personal_certs':
                $reload = false;
                $pkcs12_2nd = false;
                $signkey = false; // because 'process_import_personal_sign_certs' should take care of that
                $extrakey = false; // because these are not extra keys
                $identityID = $this->_identity->getDefault(); // keys are added in dependence of their identity
                try {
                    $pkcs12 = $this->_getImportKey('upload_key');
                    $this->_smime->addFromPKCS12($pkcs12, $this->vars->upload_key_pass, $this->vars->upload_key_pk_pass, $signkey, $extrakey, $identityID);
                    $notification->push(_('S/MIME Public/Private Keypair successfully added.'), 'horde.success');
                    if ($pkcs12_2nd = $this->_getSecondaryKey()) {
                        $this->_smime->addFromPKCS12($pkcs12, $this->vars->upload_key_pass2, $this->vars->upload_key_pk_pass2, $signkey=true, $extrakey, $identityID);
                        $notification->push(_('Secondary S/MIME Public/Private Keypair successfully added.'), 'horde.success');
                    }
                    $reload = true;
                } catch (Horde_Browser_Exception $e) {
                    if ($e->getCode() != UPLOAD_ERR_NO_FILE ||
                        !($pkcs12_2nd = $this->_getSecondaryKey())) {
                        $notification->push(_('Personal S/MIME certificates NOT imported.'), 'horde.error');
                    }
                } catch (Horde_Exception $e) {
                    $notification->push(_('Personal S/MIME certificates NOT imported: ') . $e->getMessage(), 'horde.error');
                }
                if (!$reload &&
                    ($pkcs12_2nd || ($pkcs12_2nd = $this->_getSecondaryKey()))) {
                    if (!$this->_smime->getPersonalPublicKey(0, $identityID)) {
                        $notification->push(_('Cannot import secondary personal S/MIME certificates without primary certificates.'), 'horde.error');
                    } else {
                        try {
                            $this->_smime->addFromPKCS12($pkcs12_2nd, $this->vars->upload_key_pass2, $this->vars->upload_key_pk_pass2, $signkey=true, $extrakey, $identityID);
                            $notification->push(_('Secondary S/MIME Public/Private Keypair successfully added.'), 'horde.success');
                            $reload = true;
                        } catch (Horde_Exception $e) {
                            $notification->push(_('Personal S/MIME certificates NOT imported: ') . $e->getMessage(), 'horde.error');
                        }
                    }
                }

                if ($reload) {
                    $this->_reloadWindow();
                }

                $this->vars->actionID = 'import_personal_certs';
                $this->_importKeyDialog('personal');
                break;
        }
    }

    /**
     */
    public static function url(array $opts = [])
    {
        return Horde::url('basic.php')->add('page', 'smime');
    }

    /**
     * Returns the secondary key if uploaded.
     *
     * @return string|boolean  The key contents or false if not uploaded.
     */
    protected function _getSecondaryKey()
    {
        global $notification;

        try {
            return $this->_getImportKey('upload_key2');
        } catch (Horde_Browser_Exception $e) {
            if ($e->getCode() == UPLOAD_ERR_NO_FILE) {
                return false;
            }
            $notification->push(
                _('Secondary personal S/MIME certificates NOT imported.'),
                'horde.error'
            );
        } catch (Horde_Exception $e) {
            $notification->push(
                _('Secondary personal S/MIME certificates NOT imported: ')
                    . $e->getMessage(),
                'horde.error'
            );
        }

        return false;
    }

    /**
     * Generates import key dialog.
     *
     * @param string $target  Which dialog to generate, either 'personal' or
     *                        'public'.
     */
    protected function _importKeyDialog($target)
    {
        global $notification, $page_output, $registry;

        $page_output->topbar = $page_output->sidebar = false;
        $page_output->addInlineScript([
            '$$("INPUT.horde-cancel").first().observe("click", function() { window.close(); })',
        ], true);

        /* Import CSS located with prefs CSS. */
        $p_css = new Horde_Themes_Element('prefs.css');
        $page_output->addStylesheet($p_css->fs, $p_css->uri);

        // $this->title = $target == 'personal'
        //     ? _('Import Personal S/MIME Certificate')
        //     : _('Import Public S/MIME Key');
        /* Setting target for personal, public and for extra certificates */
        switch ($target) {
            case 'personal':
                $this->title = _('Import Personal S/MIME Certificate');
                break;
            case 'extra':
                $this->title = _('Import Extra Personal S/MIME Certificates');
                // no break
            case 'identity':
                $this->title = _('Import S/MIME Certificates for new Identity');
                // no break
            default:
                $this->title = _('Import Public S/MIME Key');
                break;
        }


        /* Need to use regular status notification - AJAX notifications won't
         * show in popup windows. */
        if ($registry->getView() == Horde_Registry::VIEW_DYNAMIC) {
            $notification->detach('status');
            $notification->attach('status');
        }

        $view = new Horde_View([
            'templatePath' => IMP_TEMPLATES . '/smime',
        ]);
        $view->addHelper('Text');

        $view->reload = $this->vars->reload;
        $view->selfurl = self::url();

        $this->output = $view->render('import_' . $target . '_key');
    }

    /**
     * Reload the window.
     */
    protected function _reloadWindow()
    {
        echo Horde::wrapInlineScript([
            'opener.focus();'.
            'opener.location.href="' . base64_decode($this->vars->reload) . '";',
            'window.close();',
        ]);
        exit;
    }

    /**
     * Output text in a window.
     *
     * @param string $name   The window name.
     * @param string $msg    The text contents.
     * @param boolean $html  $msg is HTML format?
     */
    protected function _textWindowOutput($name, $msg, $html = false)
    {
        $GLOBALS['browser']->downloadHeaders($name, 'text/' . ($html ? 'html' : 'plain') . '; charset=' . 'UTF-8', true, strlen($msg));
        echo $msg;
        exit;
    }

    /**
     * Print certificate information.
     *
     * @param string $cert  The S/MIME certificate.
     */
    protected function _printCertInfo($cert = '')
    {
        $cert_info = $this->_smime->certToHTML($cert);

        $this->_textWindowOutput(
            _('S/MIME Key Information'),
            empty($cert_info) ? _('Invalid key') : $cert_info,
            !empty($cert_info)
        );
    }

    /**
     * Attempt to import a key from form/uploaded data.
     *
     * @param string $filename  Key file name.
     * @param string $key       Key string.
     *
     * @return string  The key contents.
     * @throws Horde_Browser_Exception
     */
    protected function _getImportKey($filename, $key = null)
    {
        if (!empty($key)) {
            return $key;
        }

        $GLOBALS['browser']->wasFileUploaded($filename, _('key'));
        return file_get_contents($_FILES[$filename]['tmp_name']);
    }
}
