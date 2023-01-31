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

use Horde\Util\HordeString;

/**
 * Contains code related to handling S/MIME messages within IMP.
 *
 * @author    Mike Cochrane <mike@graftonhall.co.nz>
 * @author    Jan Schneider <jan@horde.org>
 * @category  Horde
 * @copyright 2002-2017 Horde LLC
 * @license   http://www.horde.org/licenses/gpl GPL
 * @package   IMP
 */
class IMP_Smime
{
    /* Name of the S/MIME public key field in addressbook. */
    public const PUBKEY_FIELD = 'smimePublicKey';

    /* Encryption type constants. */
    public const ENCRYPT = 'smime_encrypt';
    public const SIGN = 'smime_sign';
    public const SIGNENC = 'smime_signenc';

    /* Which key to use. */
    public const KEY_PRIMARY = 0;
    public const KEY_SECONDARY = 1;
    public const KEY_SECONDARY_OR_PRIMARY = 2;

    /* The default identity that is set */
    public $defaultIdentity;

    /**
     * S/MIME object.
     *
     * @var Horde_Crypt_Smime
     */
    protected $_smime;

    /**
     * Handle for the current database connection.
     *
     * @var Horde_Db_Adapter
     */
    protected $_db;

    /**
     * Return whether PGP support is current enabled in IMP.
     *
     * @return boolean  True if PGP support is enabled.
     */
    public static function enabled()
    {
        global $conf, $prefs;

        return (!empty($conf['openssl']['path']) &&
                $prefs->getValue('use_smime') &&
                Horde_Util::extensionExists('openssl'));
    }

    /**
     * Constructor.
     *
     * @param Horde_Crypt_Smime $pgp  S/MIME object.
     */
    public function __construct(Horde_Crypt_Smime $smime, $db)
    {
        $this->_smime = $smime;
        $this->_db = $db;
    }

    /**
     * Returns the list of available encryption options for composing.
     *
     * @return array  Keys are encryption type constants, values are gettext
     *                strings describing the encryption type.
     */
    public function encryptList()
    {
        global $injector, $registry;

        $identity = $injector->getInstance('IMP_Identity');
        $identityID = $identity->getDefault();

        $ret = [];

        if ($registry->hasMethod('contacts/getField') ||
            $injector->getInstance('Horde_Core_Hooks')->hookExists('smime_key', 'imp')) {
            $ret += [
                self::ENCRYPT => _('S/MIME Encrypt Message'),
            ];
        }

        if ($this->getPersonalPrivateKey(self::KEY_PRIMARY, $identityID)) {
            $ret += [
                self::SIGN => _('S/MIME Sign Message'),
                self::SIGNENC => _('S/MIME Sign/Encrypt Message'),
            ];
        }

        return $ret;
    }

    /**
     * Adds the personal public key to the prefs.
     *
     * @param string|array $key  The public key to add.
     * @param boolean $signkey   The secondary key for signing (optional)
     * @param int $identityID    The identity for wich the public key should be added
     */
    public function addPersonalPublicKey($key, $signkey = false, $identityID=0)
    {
        global $injector, $prefs;
        // clean key if it is a string otherwise, if it is an ID (which it should be) keep it
        $val = is_array($key) ? implode('', $key) : $key;
        $val = HordeString::convertToUtf8($val);

        // NOTE: the public key does not need a field, because it will be the same as for the private key anyway

        if ($signkey === true || $signkey == self::KEY_SECONDARY) {
            $prefName = 'smime_public_sign_key';
        } else {
            $prefName = 'smime_public_key';
        }

        $prefs->setValue($prefName, $val);
    }

    /**
     * Adds the personal private key to the prefs.
     *
     * @param string|array  $key                The private key to add.
     * @param boolean       $signkey            Set this to indicate the secondary key for signing
     * @param boolean       $calledFromSetSmime This is to stop unneded notifications
     * @param int           $identityID         The identity for wich the private key should be added
     */
    public function addPersonalPrivateKey($key, $signkey = false, $calledFromSetSmime = false, $identityID=0)
    {
        // TODO: find way to only add an id to the array of prefs..

        global $prefs, $injector;
        // clean key if it is a string otherwise, if it is an ID (which it should be) keep it
        $val = is_array($key) ? implode('', $key) : $key;
        $val = HordeString::convertToUtf8($val);

        // get the keyid to set it to the identites array in prefs
        $keyID = $this->privateKeyExists($key, $identityID, true, true);

        // use identity to set the peronal private key to the serialized identity array
        $identity = $injector->getInstance('IMP_Identity');

        // check if a private key already exists
        $check  = $prefs->getValue('smime_private_key');

        // it there is a private key, these will be unset first and then the new one will be added to the database and its id will be added to the prefs array
        // unsetting

        if (!empty($check) && $signkey == false) {
            $this->unsetSmimePersonal($signkey, $calledFromSetSmime, $identityID);
        }

        // setting id to prefstables, only if $key is an integer
        if (!empty($keyID)) {
            if ($signkey === true || $signkey == self::KEY_SECONDARY) {
                $prefName = 'smime_private_sign_key';
                $identity->setValue('privsignkey', $keyID, $identityID);
            } else {
                $prefName = 'smime_private_key';
                $identity->setValue('privkey', $keyID, $identityID);
            }
            $prefs->setValue($prefName, $val);
            $identity->save();
        }
    }

    /**
     * Adds extra personal keys to the extra keys table.
     *
     * @param string|array  $key            The private key to add.
     * @param string|array  $key            The public key to add.
     * @param string        $password       The password for the private key to add.
     * @param string        $pref_name      To be removed... TODO.@param string|array
     * @param string        $identity       The name of the identity to save the keys for
     * @param bool          $identity_used  Marks the keys as the one that is being used
     */
    public function addExtraPersonalKeys(
        $private_key,
        $public_key,
        $password,
        $pref_name = 'smime_private_key',
        $identityID=0,
        $identity_used=false
    ) {
        global $notification;
        /* Get the user_name  */
        // TODO: is there a way to only use prefs?
        $user_name = $GLOBALS['registry']->getAuth();

        // Encrypt the password
        $key = $GLOBALS['conf']['secret_key'];
        $blowfish = new Horde_Crypt_Blowfish($key);
        $encryptedPassword = $blowfish->encrypt($password);
        $encryptedPassword = base64_encode($encryptedPassword);

        if ($this->privateKeyExists($private_key, $identityID)) {
            $notification->push(_('Key is allready in the Database'), 'horde.success');
            return false;
        }

        if (!empty($public_key) && !empty($private_key) && !empty($encryptedPassword)) {
            $query = 'INSERT INTO imp_smime_extrakeys (pref_name, user_name, private_key, public_key, privatekey_passwd, identity, identity_used) VALUES (?, ?, ?, ?, ?, ?, ?)';
            $values = [$pref_name, $user_name, $private_key, $public_key, $encryptedPassword, $identityID, $identity_used];
            $this->_db->insert($query, $values);
            return true;
        }
    }

    /**
     * Adds a list of additional certs to the prefs.
     *
     * @param string|array  $key       The additional certifcate(s) to add.
     * @param boolean       $signkey   Is this the secondary key for signing?
     */
    public function addAdditionalCert($key, $signkey = false)
    {
        $prefName = $signkey ? 'smime_additional_sign_cert' : 'smime_additional_cert';
        $val = is_array($key) ? implode('', $key) : $key;
        $val = HordeString::convertToUtf8($val);
        $GLOBALS['prefs']->setValue($prefName, $val);
    }

    /**
     * Returns the personal public key from the prefs.
     *
     * @param integer   $signkey:    One of the IMP_Sime::KEY_* constants.
     * @param integer   $identityID: The Identity to retrieve the personal public key from.
     *
     * @return string  The personal S/MIME public key.
     */
    public function getPersonalPublicKey($signkey = self::KEY_PRIMARY, $identityID = 0)
    {
        global $injector;

        $identity = $injector->getInstance('IMP_Identity');

        // note: this is getting the privatekeyid from the identities array as this id suffices (no extra pub-id-key needed)
        if ($signkey === self::KEY_SECONDARY) {
            $keyID = $identity->getValue('privsignkey', $identityID);
        } else {
            $keyID = $identity->getValue('privkey', $identityID);
        }

        // with keyID get key from extratables
        $key = $this->getExtraPublicKey($keyID);
        return $key;
    }

    /**
     * Returns the personal private key from the prefs.
     *
     * @param int $signkey:       One of the IMP_Sime::KEY_* constants.
     * @param int $identityID:    The identity for wich the key should be gotten. TODO: this not yet implemented!
     *
     * @return string  The personal S/MIME private key.
     */
    public function getPersonalPrivateKey($signkey = self::KEY_PRIMARY, $identityID=0)
    {
        global $injector;

        $identity = $injector->getInstance('IMP_Identity');
        if ($signkey === self::KEY_SECONDARY) {
            $keyID = $identity->getValue('privsignkey', $identityID);
        } else {
            $keyID = $identity->getValue('privkey', $identityID);
        }

        // with keyID get key from extratables
        $key = $this->getExtraPrivateKey($keyID);
        return $key;

        // TODO: Problem: current users will have their keys on that spot and will loose them! Need a migrate script!
    }

    /**
     * Retrieves a specific public key from the extrakeys table or throws an exception.
     *
     * @param int       $privateKeyId: get the public key of of the privatekey (its id)
     * @param string    $prefName: indicates that a key is for sining
     * @param int       $identityID: The identity ID to retrieve the extra key for
     *
     * @return string   Specific S/MIME private key.
     * @throws Horde_Db_Exception
     *
     * TODO: need to remove the $prefName thingie for extrakeys table, makes no sense
     */
    public function getExtraPublicKey($privateKeyId, $prefName = 'smime_private_key', $identityID=0)
    {
        // TODO: there is no use for prefName or identityID! it has to be removed from all calls to this functino
        // reason: the keyid is given and the username, no need for anything else

        // Build the SQL query
        $query = 'SELECT private_key_id, public_key FROM imp_smime_extrakeys WHERE private_key_id=?';
        $values = [$privateKeyId];
        // Run the SQL query
        $result = $this->_db->selectOne($query, $values); // returns one key
        return $result['public_key'];
    }

    /**
     * Retrieves a specific private key from the extrakeys table.
     *
     * @param int       $id: id of the key to search for
     * @param string    $prefname: currently set to discern between sing keys and normal keys
     * @param int       $identityID: the identity to look for
     *
     * @return string   Specific S/MIME private key.
     * @throws Horde_Db_Exception
     */
    public function getExtraPrivateKey($id, $prefName = 'smime_private_key', $identityID = 0)
    {
        // Build the SQL query
        $query = 'SELECT private_key_id, private_key FROM imp_smime_extrakeys WHERE private_key_id=?';
        $values = [$id];

        // Run the SQL query
        $result = $this->_db->selectOne($query, $values); // returns one key
        return $result['private_key'];
    }

    /**
     * Get private key id of the set Personal Certificate (if it exists in the database)
     *
     * @param string    $prefname: currently set to discern between sing keys and normal keys
     * @param int       $identityID: the identity to look for
     *
     * @return int id of extra private certificate in DB
     * @throws Horde_Db_Exception
     */
    public function getSetPrivateKeyId($signkey = self::KEY_PRIMARY, $identityID=0)
    {
        {
            /* Get the user_name and personal certificate if existant */
            // TODO: is there a way to only use prefs?
            $user_name = $GLOBALS['registry']->getAuth();
            $personalCertificate = $this->getPersonalPrivateKey($signkey, $identityID);

            //check the database and if keys are the same
            $returnvalue = $this->privateKeyExists($personalCertificate, $identityID, $returnID=true, $returnLastIdInTable=false);

            if (isset($returnvalue) && !empty($returnvalue)) {
                return $returnvalue;
            } else {
                return false;
            }
        }
    }

    /**
     * Check if the private keys allready exist.
     * Example: if the key already exists, there is no need to load it into the database again
     *
     * @param   string    $personalCertificate: the personal certificate to look for
     * @param   int       $idenitytId: the id of the identity to look for
     * @param   bool      $returnID: returns the id of the private key that was found
     * @param   bool      $returnLastIdInTable: returns the last ID that was set in the table
     *
     * @return  bool|int      if private key is there or not, if an ID should be returned
     * @throws Horde_Db_Exception
     */
    public function privateKeyExists($personalCertificate, $identityID, $returnID=false, $returnLastIdInTable=false)
    {
        /* Get the user_name  */
        // TODO: is there a way to only use prefs?
        $user_name = $GLOBALS['registry']->getAuth();

        // Build the SQL query
        $query = 'SELECT private_key_id, private_key FROM imp_smime_extrakeys WHERE user_name=? AND identity=?';
        $values = [$user_name, $identityID];

        // Run the SQL query
        $result = $this->_db->selectAll($query, $values); // returns an array with keys
        if (!empty($result)) {
            // check if privatekeys are the same
            foreach ($result as $key => $value) {
                $diff = strcmp($value, $personalCertificate);
                if ($value['private_key'] === $personalCertificate || isset($diff) && $diff == 0) {
                    if ($returnID === true) {
                        return $value['private_key_id'];
                    }
                    return true;
                }
            }
        } else {
            if ($returnLastIdInTable) {
                // return last id in the table, else if table is empty return the index 0
                !empty($result) ? $result = array_key_last($result) : $result = 0;
                return $result;
            }
            return false;
        }
    }


    /**
     * Retrieves all public and private keys and their aliases from imp_smime_extrakeys table.
     *
     * @param string    $prefname:      To discern between sing keys and normal keys
     * @param int       $identityID:    The identity to look for
     *
     * @return array  All S/MIME private keys available.
     * @throws Horde_Db_Exception
     */
    public function listAllKeys($prefName = 'smime_private_key', $identity = 0)
    {
        /* Get the user_name  */
        // TODO: is there a way to only use prefs?
        // TODO: prefname can be removed here from this function call as well
        $user_name = $GLOBALS['registry']->getAuth();

        // Build the SQL query
        $query = 'SELECT private_key_id, private_key, public_key, alias FROM imp_smime_extrakeys WHERE user_name=? AND identity=?';
        $values = [$user_name, $identity];

        // Run the SQL query
        $result = $this->_db->selectAll($query, $values); // returns an array with keys
        return $result;
    }


    /**
     * Retrieves all private key ids from imp_smime_extrakeys table.
     *
     * @param string    $prefNasme: defines if key is for encrypting or for signing
     * @param integer   $identityID: the identity to retrieve the keys from
     *
     * @return array  All S/MIME private keys available.
     * @throws Horde_Db_Exception
     */
    public function listPrivateKeyIds($prefName = 'smime_private_key', $identityID = 0)
    {
        /* Get the user_name  */
        // TODO: is there a way to only use prefs?
        $user_name = $GLOBALS['registry']->getAuth('original');

        $GLOBALS['session']->get('horde', 'auth/userId');

        // Build the SQL query

        $query = 'SELECT private_key_id FROM imp_smime_extrakeys WHERE pref_name=? AND user_name=? AND identity=?';
        $values = [$prefName, $user_name, $identityID];

        // Run the SQL query
        $result = $this->_db->selectValues($query, $values); // returns an array with keys
        return $result;
    }

    /**
     * Setting an alias in the database
     *
     * @param int       $keyid:      Id of privatekey to set the alias for
     * @param string    $alias:      Alias to set
     * @param int       $identityID: The identity for which the alias should be set
     *
     * @return bool|error returns either true, false or an error if database insertion failed
     */
    public function updateAlias($keyid, $alias, $identityID)
    {
        $query = 'UPDATE imp_smime_extrakeys SET alias = ? WHERE private_key_id = ? AND identity = ?';
        $values = [$alias, $keyid, $identityID];
        $this->_db->insert($query, $values);
    }

    /**
     * Getting an alias from the database
     *
     * @param int $keyid to find the alias belong to the key
     *
     * @return string|bool returns an alias (name) of the certification or false if nothing is returned
     */
    public function getAlias($keyid, $identityID)
    {
        $query = 'SELECT alias FROM imp_smime_extrakeys WHERE private_key_id=? AND identity=?';
        $values = [$keyid,  $identityID];
        $result = $this->_db->selectValue($query, $values, $identityID);

        // checking if $result is empty
        if (empty($result)) {
            return false;
        } else {
            return $result;
        }
    }


    /**
     * Setting a new Personal Certificate and belonging Public Certificate:
     * Transfers a Certificate and belonging Public Certificate from the Extra Keys table to Horde_Prefs
     *
     * @param int       $keyid:         Returns the key from the keyid
     * @param int       $signkey:       Sets a sign key, per default a personal (primary) key is set
     * @param int       $identityID:    The identity to look for
     *
     */
    public function setSmimePersonal($keyid, $signkey=self::KEY_PRIMARY, $identityID=0)
    {
        if ($signkey == self::KEY_PRIMARY) {
            $prefName = 'smime_private_key';
        } elseif ($signkey == self::KEY_SECONDARY) {
            $prefName = 'smime_private_sign_key';
        }

        // Warns unsetSmime functions that no notifications are needed
        $calledFromSetSmime = true;
        // find the private key that has been selected (NB: do not care if the key is a sign key or not TODO: so no prefname needed?)
        $newprivatekey = $this->getExtraPrivateKey($keyid);
        $newpublickey = $this->getExtraPublicKey($keyid); // buggy over here: need to remove singkey stuff in parameters

        // check if a personal certificate is set
        $check = null;
        $check = $this->getPersonalPrivateKey(self::KEY_PRIMARY, $identityID); // this is not gonna work or is it?

        $keyExists = $this->privateKeyExists($check, $identityID);
        
        // check if there is a personal Certificate set
        if (!empty($check)) {
            // if there is a personal certificate, copy the personal certificate itself or the singkey (depending on wheater it is set) to the database otherwise discontinue the action
            if ($keyExists) { // if the key exists in the database just add (overwrite) the key to the prefs table
                $this->addPersonalPrivateKey($newprivatekey, $signkey, $calledFromSetSmime, $identityID);
                $this->addPersonalPublicKey($newpublickey, $signkey, $identityID);
                return;
            }
            // if the key is not in the database, first unset the key (which copies it to the database) and than add (overwrite) the new keys in the prefs table
            // Note:
            // - $calledFromSetSmime: This variable is used because setSmimePersonal() adds certifactes from the database allready.
            //   So there is no need to check for a correct password, as it should allready have locked the certificates in the database.
            // - Setting $calledFromSetSmime = true stopps notifications from poping up.
            // TODO:
            // - the singkey stuff is very confusing, needs to be refactored
            if ($this->unsetSmimePersonal($signkey = self::KEY_PRIMARY, $calledFromSetSmime, $identityID) != false) {
                $this->addPersonalPrivateKey($newprivatekey, $signkey, $calledFromSetSmime);
                $this->addPersonalPublicKey($newpublickey, $signkey);
                return;
            }
            // otherwise do nothing
            return;
        }
        // if not: import it. Note: if a newly imported but yet non-existant key is to be added, $calledFromSetSmime is not set to true, because password checks need to happen
        $this->addPersonalPrivateKey($newprivatekey, $signkey, $calledFromSetSmime=false, $identityID);
        $this->addPersonalPublicKey($newpublickey, $signkey, $identityID);
    }

    /**
     * Setting a new certificate for signing SMIME mails
     *
     * @param int $keyid:       To inform which key should be set as a secondary signkey
     * @param int $identityID:  The identity to look for
     */
    public function setSmimeSecondary($keyid, $identityID)
    {
        $this->setSmimePersonal($keyid, self::KEY_SECONDARY, $identityID);
    }

    /**
     * Unsetting a Personal Certificate and belonging Public Certificate:
     * Transfers a Personal Certificate and belonging Public Certificate to the Extra Keys table in the DB
     *
     * @param int   $singkey:               Defines the key to be processed. Per default it is the personal (primary) key, when e.g. set to self::KEY_SECONDARY the secondary sign key will be processed
     * @param bool  $calledFromSetSmime:    Disables notifications for unset passwords: If the function is called from setSmimePersonal there is no reason to check for a password, because the key and the password is set in the database allready.
     * @param int   $identityID:            The identity for wich the key should be set
     */
    public function unsetSmimePersonal($signkey = self::KEY_PRIMARY, $calledFromSetSmime = false, $identityID=0)
    {
        global $notification;

        // get current personal certificates
        $privateKey = $this->getPersonalPrivateKey($signkey, $identityID);
        $publicKey = $this->getPersonalPublicKey($signkey, $identityID);

        // get password, hash it and save it to the table
        $password = $this->getPassphrase($signkey);
        if ($password == false || is_null($password) || empty($password)) {
            // check if unsetSmimePersonal is called from setSmime, where passwords are set in the DB allready, and there is no need to push any notifications
            if ($calledFromSetSmime == false) {
                $notification->push(
                    _('Please set a correct password before unsetting the keys.'),
                    'horde.error'
                );
            }
            return false;
        }

        // push these to the extra keys table
        if (!empty($privateKey) && !empty($publicKey) && !empty($password)) {
            if ($this->addExtraPersonalKeys($privateKey, $publicKey, $password, 'smime_private_key', $identityID)) {
                try {
                    $this->deletePersonalKeys($signkey, $identityID);
                    $notification->push(
                        _('S/MIME Certificate unset and successfully transfered to extra keys.'),
                        'horde.success'
                    );
                    return true;
                } catch (\Throwable $th) {
                    $notification->push(
                        _('S/MIME Certificates were not proberly deleted from database.'),
                        'horde.error'
                    );
                    throw $th;
                }
            } else {
                // unsetting can be done because certificate is in the database anyway
                $this->deletePersonalKeys($signkey, $identityID);
            }
        }
    }

    /**
     * Unsetting a Certificate for Singing and transerfing it to extra tables
     *
     * @param bool  $calledFromSetSmime:    To inform the method that it does not need to check for a password again.
     * @param int   $identityID:            The identity to look for.
     */
    public function unsetSmimeSecondary($calledFromSetSmime, $identityID=0)
    {
        $this->unsetSmimePersonal(self::KEY_SECONDARY, $calledFromSetSmime, $identityID);
    }

    /**
     * Returns any additional certificates from the prefs.
     *
     * @param integer $signkey  One of the IMP_Sime::KEY_* constants.
     * @param integer $identityID the id of the identity to get the key from.
     *
     * @return string  Additional signing certs for inclusion.
     */
    public function getAdditionalCert($signkey = self::KEY_PRIMARY, $identityID=0)
    {
        global $injector;

        $identity = $injector->getInstance('IMP_Identity');

        if ($signkey == self::KEY_PRIMARY || $signkey == self::KEY_SECONDARY_OR_PRIMARY) {
            $key = $identity->getValue('pubsignkey', $identityID);
        } else {
            $key = $identity->getValue('pubkey', $identityID);
        }

        return $key;
    }

    /**
     * Deletes the specified personal keys from the prefs.
     *
     * @param boolean   $signkey:     Return the secondary key for signing?
     * @param int       $identityID:  The identity to look for.
     */
    public function deletePersonalKeys($signkey = false, $identityID=0)
    {
        global $prefs, $injector;

        // We always delete the secondary keys because we cannot have them
        // without primary keys.
        $prefs->setValue('smime_public_sign_key', '');
        $prefs->setValue('smime_private_sign_key', '');
        $prefs->setValue('smime_additional_sign_cert', '');
        // also delete the ids set in the identity prefs array
        $identity = $injector->getInstance('IMP_Identity');
        $identity->setValue('privsignkey', '', $identityID);
        $identity->setValue('pubsignkey', '', $identityID);

        if (!$signkey) {
            $prefs->setValue('smime_public_key', '');
            $prefs->setValue('smime_private_key', '');
            $prefs->setValue('smime_additional_cert', '');
            // also delete the ids set in the identity prefs array
            $identity->setValue('pubkey', '', $identityID);
            $identity->setValue('privkey', '', $identityID);
        }
        $identity->save();
        $this->unsetPassphrase($signkey);
    }

    /**
     * Deletes the specified extra keys from the extra-keys-table.
     *
     * @param int     $private_key_id:  The ID of the privatekey to look for.
     * @param boolean $signkey:         Return the secondary key for signing?
     */
    public function deleteExtraKey($private_key_id, $signkey = false)
    {
        /* Build the SQL query. */
        $query = 'DELETE FROM imp_smime_extrakeys WHERE private_key_id = ?';
        $values = [ $private_key_id ];

        $this->_db->delete($query, $values);
    }

    /**
     * Adds a public key to an address book.
     *
     * @param string $cert  A public certificate to add.
     *
     * @throws Horde_Exception
     */
    public function addPublicKey($cert)
    {
        global $prefs, $registry;

        [$name, $email] = $this->publicKeyInfo($cert);

        $registry->call(
            'contacts/addField',
            [
                $email,
                $name,
                self::PUBKEY_FIELD,
                $cert,
                $prefs->getValue('add_source'),
            ]
        );
    }

    /**
     * Returns information about a public certificate.
     *
     * @param string $cert  The public certificate.
     *
     * @return array  Two element array: the name and e-mail for the cert.
     * @throws Horde_Crypt_Exception
     */
    public function publicKeyInfo($cert)
    {
        /* Make sure the certificate is valid. */
        $key_info = openssl_x509_parse($cert);
        if (!is_array($key_info) || !isset($key_info['subject'])) {
            throw new Horde_Crypt_Exception(_('Not a valid public key.'));
        }

        /* Add key to the user's address book. */
        $email = $this->_smime->getEmailFromKey($cert);
        if (is_null($email)) {
            throw new Horde_Crypt_Exception(
                _('No email information located in the public key.')
            );
        }

        /* Get the name corresponding to this key. */
        if (isset($key_info['subject']['CN'])) {
            $name = $key_info['subject']['CN'];
        } elseif (isset($key_info['subject']['OU'])) {
            $name = $key_info['subject']['OU'];
        } else {
            $name = $email;
        }

        return [$name, $email];
    }

    /**
     * Returns the params needed to encrypt a message being sent to the
     * specified email address(es).
     *
     * @param Horde_Mail_Rfc822_List $addr  The recipient addresses.
     *
     * @return array  The list of parameters needed by encrypt().
     * @throws Horde_Crypt_Exception
     */
    protected function _encryptParameters(Horde_Mail_Rfc822_List $addr)
    {
        return [
            'pubkey' => array_map(
                [$this, 'getPublicKey'],
                $addr->bare_addresses
            ),
            'type' => 'message',
        ];
    }

    /**
     * Retrieves a public key by e-mail.
     *
     * The key will be retrieved from a user's address book(s).
     *
     * @param string $address  The e-mail address to search for.
     *
     * @return string  The S/MIME public key requested.
     * @throws Horde_Exception
     */
    public function getPublicKey($address)
    {
        global $injector, $registry;

        try {
            $key = $injector->getInstance('Horde_Core_Hooks')->callHook(
                'smime_key',
                'imp',
                [$address]
            );
            if ($key) {
                return $key;
            }
        } catch (Horde_Exception_HookNotSet $e) {
        }

        $contacts = $injector->getInstance('IMP_Contacts');

        try {
            $key = $registry->call(
                'contacts/getField',
                [
                    $address,
                    self::PUBKEY_FIELD,
                    $contacts->sources,
                    true,
                    true,
                ]
            );
        } catch (Horde_Exception $e) {
            /* See if the address points to the user's public key. */
            // check if this method is used to get keys of an identity
            $identity = $injector->getInstance('IMP_Identity');
            $identityID = $identity->getDefault();

            $personal_pubkey = $this->getPersonalPublicKey(self::KEY_SECONDARY_OR_PRIMARY, $identityID);

            if (!empty($personal_pubkey) &&
                $injector->getInstance('IMP_Identity')->hasAddress($address)) {
                return $personal_pubkey;
            }

            throw $e;
        }

        /* If more than one public key is returned, just return the first in
         * the array. There is no way of knowing which is the "preferred" key,
         * if the keys are different. */
        return is_array($key) ? reset($key) : $key;
    }

    /**
         * Retrieves all public keys from a user's address book(s).
         *
         * @return array  All S/MIME public keys available.
         * @throws Horde_Crypt_Exception
         */
    public function listPublicKeys()
    {
        global $injector, $registry;

        $sources = $injector->getInstance('IMP_Contacts')->sources;

        if (empty($sources)) {
            return [];
        }

        return $registry->call(
            'contacts/getAllAttributeValues',
            [self::PUBKEY_FIELD, $sources]
        );
    }

    /**
     * Deletes a public key from a user's address book(s) by e-mail.
     *
     * @param string $email  The e-mail address to delete.
     *
     * @throws Horde_Crypt_Exception
     */
    public function deletePublicKey($email)
    {
        global $injector, $registry;

        $registry->call(
            'contacts/deleteField',
            [
                $email,
                self::PUBKEY_FIELD,
                $injector->getInstance('IMP_Contacts')->sources,
            ]
        );
    }

    /**
     * Returns the parameters needed for signing a message.
     *
     * @return array  The list of parameters needed by encrypt().
     */
    protected function _signParameters()
    {
        //TODO: use a roto variable of the class instead of calling this over and over again
        global $injector;
        $identity = $injector->getInstance('IMP_Identity');
        $identityID = $identity->getDefault();
        $pubkey = $this->getPersonalPublicKey(true, $identityID);
        $additional = [];
        if ($pubkey) {
            $additional[] = $this->getPersonalPublicKey(self::KEY_PRIMARY, $identityID);
            $secondary = true;
        } else {
            $pubkey = $this->getPersonalPublicKey(self::KEY_PRIMARY, $identityID);
            $secondary = false;
        }
        $additional[] = $this->getAdditionalCert($secondary, $identityID);
        if ($secondary) {
            $additional[] = $this->getAdditionalCert();
        }
        return [
            'type' => 'signature',
            'pubkey' => $pubkey,
            'privkey' => $this->getPersonalPrivateKey($secondary, $identityID),
            'passphrase' => $this->getPassphrase($secondary),
            'sigtype' => 'detach',
            'certs' => implode("\n", $additional),
        ];
    }

    /**
     * Verifies a signed message with a given public key.
     *
     * @param string $text  The text to verify.
     *
     * @return stdClass  See Horde_Crypt_Smime::verify().
     * @throws Horde_Crypt_Exception
     */
    public function verifySignature($text)
    {
        global $conf;

        return $this->_smime->verify(
            $text,
            empty($conf['openssl']['cafile'])
                ? []
                : $conf['openssl']['cafile']
        );
    }

    /**
     * Decrypts a message with user's public/private keypair.
     *
     * @param string    $text:           The text to decrypt.
     * @param integer   $differentKey:   The ID of an extra key, set in extra table of the database.
     * @param integer   $identityID:     The ID of a specific identity to decrypt for.
     *
     * @return string  See Horde_Crypt_Smime::decrypt().
     * @throws Horde_Crypt_Exception
     */
    public function decryptMessage($text, $differentKey = null, $identityID = null)
    {
        global $injector;

        // get the specified identity or the default identity
        if ($identityID === null) {
            $identity = $injector->getInstance('IMP_Identity');
            $identityID = $identity->getDefault();
        }

        if ($differentKey === null) {
            $value = $this->_smime->decrypt($text, [
                'type' => 'message',
                'pubkey' => $this->getPersonalPublicKey(self::KEY_PRIMARY, $identityID),
                'privkey' => $this->getPersonalPrivateKey(self::KEY_PRIMARY, $identityID),
                'passphrase' => $this->getPassphrase(),
            ]);
            return $value;
        } else {
            $value = $this->_smime->decrypt($text, [
                'type' => 'message',
                'pubkey' => $this->getExtraPublicKey($differentKey, $identityID),
                'privkey' => $this->getExtraPrivateKey($differentKey, $identityID),
                'passphrase' => $this->getPassphrase(null, $differentKey), // create get pasExtraKeyPassphrase()?
            ]);
            return $value;
        }
    }

    /**
     * Returns the user's passphrase from the session cache.
     *
     * @param integer       $signkey      One of the IMP_Sime::KEY_* constants.
     * @param integer|null  $differentkey If set, this integer will be used to find a privatekey id from the extrakeys table in the database.
     *
     * @return mixed  The passphrase, if set.  Returns false if the passphrase
     *                has not been loaded yet.  Returns null if no passphrase
     *                is needed.
     */
    public function getPassphrase($signkey = self::KEY_PRIMARY, $differentKey = null)
    {
        global $prefs, $session, $injector;

        // TODO: call identity here or ask for it as a parameter of the function?
        $identity = $injector->getInstance('IMP_Identity');
        $identityID = $identity->getDefault();

        if ($differentKey === null) {
            if ($signkey == self::KEY_SECONDARY_OR_PRIMARY || $signkey == self::KEY_SECONDARY) {
                if ($private_key = $this->getPersonalPrivateKey(self::KEY_SECONDARY, $identityID)) {
                    $signkey = self::KEY_SECONDARY;
                } else {
                    $private_key = $this->getPersonalPrivateKey(self::KEY_PRIMARY, $identityID);
                    $signkey = self::KEY_PRIMARY;
                }
            } else {
                $private_key = $this->getPersonalPrivateKey($signkey, $identityID);
            }
        } else {
            // TODO: Check if it is necessary to take care of secondary keys in extratables
            $private_key = $this->getExtraPrivateKey($differentKey, $identityID);
        }

        if (empty($private_key)) {
            return false;
        }

        if ($differentKey === null) {
            $suffix = $signkey ? '_sign' : '';
            if ($session->exists('imp', 'smime_passphrase' . $suffix)) {
                return $session->get('imp', 'smime_passphrase' . $suffix);
            }

            if (!$session->exists('imp', 'smime_null_passphrase' . $suffix)) {
                $session->set(
                    'imp',
                    'smime_null_passphrase' . $suffix,
                    $this->_smime->verifyPassphrase($private_key, null)
                        ? null
                        : false
                );
            }
            $result = $session->get('imp', 'smime_null_passphrase' . $suffix);
        } else {
            // TODO: take care of extra sign keys
            // get passphrase for specific key in the extra tables

            // Build the SQL query
            $query = 'SELECT privatekey_passwd FROM imp_smime_extrakeys WHERE private_key_id=? AND IDENTITY=?';
            $values = [$differentKey, $identityID];
            // Run the SQL query
            $result = $this->_db->selectValue($query, $values);

            # decrypt the hashed value here
            $key = $GLOBALS['conf']['secret_key'];
            $blowfish = new Horde_Crypt_Blowfish($key);
            $result = base64_decode($result);
            $result = $blowfish->decrypt($result);
        }
        return $result;
    }

    /**
     * Stores the user's passphrase in the session cache.
     *
     * @param string $passphrase  The user's passphrase.
     * @param integer $signkey    One of the IMP_Sime::KEY_* constants.
     *
     * @return boolean  Returns true if correct passphrase, false if incorrect.
     */
    public function storePassphrase($passphrase, $signkey = self::KEY_PRIMARY)
    {
        global $session, $injector;

        // TODO: call identity here or ask for it as a parameter of the function?
        $identity = $injector->getInstance('IMP_Identity');
        $identityID = $identity->getDefault();

        if ($signkey == self::KEY_SECONDARY_OR_PRIMARY) {
            if ($key = $this->getPersonalPrivateKey(self::KEY_SECONDARY, $identityID)) {
                $signkey = self::KEY_SECONDARY;
            } else {
                $key = $this->getPersonalPrivateKey(self::KEY_PRIMARY, $identityID);
                $signkey = self::KEY_PRIMARY;
            }
        } else {
            $key = $this->getPersonalPrivateKey($signkey, $identityID);
        }
        if ($this->_smime->verifyPassphrase($key, $passphrase) !== false) {
            $session->set(
                'imp',
                $signkey ? 'smime_passphrase_sign' : 'smime_passphrase',
                $passphrase,
                $session::ENCRYPT
            );
            return true;
        }

        return false;
    }

    /**
     * Clears the passphrase from the session cache.
     *
     * @param boolean $signkey    Is this the secondary key for signing?
     */
    public function unsetPassphrase($signkey = false)
    {
        global $session;

        if ($signkey) {
            $session->remove('imp', 'smime_null_passphrase_sign');
            $session->remove('imp', 'smime_passphrase_sign');
        } else {
            $session->remove('imp', 'smime_null_passphrase');
            $session->remove('imp', 'smime_passphrase');
        }
    }

    /**
     * Encrypts a MIME part using S/MIME using IMP defaults.
     *
     * @param Horde_Mime_Part $mime_part     The object to encrypt.
     * @param Horde_Mail_Rfc822_List $recip  The recipient address(es).
     *
     * @return Horde_Mime_Part  See Horde_Crypt_Smime::encryptMIMEPart().
     * @throws Horde_Crypt_Exception
     */
    public function encryptMimePart(
        $mime_part,
        Horde_Mail_Rfc822_List $recip
    ) {
        return $this->_smime->encryptMIMEPart(
            $mime_part,
            $this->_encryptParameters($recip)
        );
    }

    /**
     * Signs a MIME part using S/MIME using IMP defaults.
     *
     * @param MIME_Part $mime_part  The MIME_Part object to sign.
     *
     * @return Horde_Mime_Part  See Horde_Crypt_Smime::signMIMEPart().
     * @throws Horde_Crypt_Exception
     */
    public function signMimePart($mime_part)
    {
        return $this->_smime->signMIMEPart(
            $mime_part,
            $this->_signParameters()
        );
    }

    /**
     * Signs and encrypts a MIME part using S/MIME using IMP defaults.
     *
     * @param Horde_Mime_Part $mime_part     The object to sign and encrypt.
     * @param Horde_Mail_Rfc822_List $recip  The recipient address(es).
     *
     * @return Horde_Mime_Part  See
     *                          Horde_Crypt_Smime::signAndencryptMIMEPart().
     * @throws Horde_Crypt_Exception
     */
    public function signAndEncryptMimePart(
        $mime_part,
        Horde_Mail_Rfc822_List $recip
    ) {
        return $this->_smime->signAndEncryptMIMEPart(
            $mime_part,
            $this->_signParameters(),
            $this->_encryptParameters($recip)
        );
    }

    /**
     * Stores the public/private/additional certificates in the preferences
     * from a given PKCS 12 file.
     *
     * TODO: Should keys be added to the extra table per default?
     *
     * @param string $pkcs12        The PKCS 12 data.
     * @param string $password      The password of the PKCS 12 file.
     * @param string $pkpass        The password to use to encrypt the private key.
     * @param boolean $signkey      Is this the secondary key for signing?
     * @param boolean $extrakey     Specifies if the key should be added to the extrakeys table
     * @param integer $identityID   The identity to look for.
     *
     * @throws Horde_Crypt_Exception
     */
    public function addFromPKCS12(
        $pkcs12,
        $password,
        $pkpass = null,
        $signkey = false,
        $extrakey = false,
        $identityID = 0,
        $identity_used = false
    ) {
        global $conf, $notification;

        $sslpath = empty($conf['openssl']['path'])
            ? null
            : $conf['openssl']['path'];

        $params = ['sslpath' => $sslpath, 'password' => $password];
        if (!empty($pkpass)) {
            $params['newpassword'] = $pkpass;
        }

        $keysinfos = $this->_smime->parsePKCS12Data($pkcs12, $params);

        // add keys to extra table
        $result = $this->addExtraPersonalKeys($keysinfos->private, $keysinfos->public, $password, $pref_name = 'smime_private_key', $identityID, $identity_used);

        if ($result) {
            $notification->push(_('S/MIME Public/Private Keypair successfully added to exra keys in keystore.'), 'horde.success');
        }

        if ($extrakey === false) {
            // get id for newly added key:
            // if the private key does not exists, get newest id to add (see method parameters of privateKeyExists)
            // else do nothing ;)

            if (!$this->privateKeyExists($keysinfos->private, $identityId)) {
                $id = $this->privateKeyExists($keysinfos->private, $identityId, false, true);

                // add id to the identities (serialized array) in prefs
                $this->addPersonalPrivateKey($id, $signkey, $calledFromSetSmime = false, $identityID);
                $this->addPersonalPublicKey($id, $signkey, $identityID);
                //TODO: This has to be checked again... not sure the method is needed anymore at all
                //$this->addAdditionalCert($keysinfos->certs, $signkey, $identityID);
            }
        }
    }

    /**
     * Extracts the contents from signed S/MIME data.
     *
     * @param string $data  The signed S/MIME data.
     *
     * @return string  The contents embedded in the signed data.
     * @throws Horde_Crypt_Exception
     */
    public function extractSignedContents($data)
    {
        global $conf;

        $sslpath = empty($conf['openssl']['path'])
            ? null
            : $conf['openssl']['path'];

        return $this->_smime->extractSignedContents($data, $sslpath);
    }

    /**
     * Checks for the presence of the OpenSSL extension to PHP.
     *
     * @throws Horde_Crypt_Exception
     */
    public function checkForOpenSsl()
    {
        $this->_smime->checkForOpenSSL();
    }

    /**
     * Converts a PEM format certificate to readable HTML version.
     *
     * @param string $cert  PEM format certificate.
     *
     * @return string  HTML detailing the certificate.
     */
    public function certToHTML($cert)
    {
        return $this->_smime->certToHTML($cert);
    }

    /**
     * Extracts the contents of a PEM format certificate to an array.
     *
     * @param string $cert  PEM format certificate.
     *
     * @return array  All extractable information about the certificate.
     */
    public function parseCert($cert)
    {
        return $this->_smime->parseCert($cert);
    }
}
