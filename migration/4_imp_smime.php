<?php
/**
 * Create IMP SMIME tables.
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author   Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  IMP
 */
class ImpSMIME extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        // Create: imp_smime_extrakeys
        $tableList = $this->tables();
        if (!in_array('imp_smime_extrakeys', $tableList)) {
            $t = $this->createTable('imp_smime_extrakeys', ['autoincrementKey' => 'private_key_id']);
            $t->column('pref_name', 'string', ['limit' => 50, 'null' => false]);
            $t->column('user_name', 'string', ['limit' => 50,'null' => false]);
            $t->column('private_key', 'binary', ['null' => false]);
            $t->column('public_key', 'binary', ['null' => true]);
            $t->column('privatekey_passwd', 'string', ['limit' => 50,'null' => true]);
            $t->column('alias', 'string', ['limit' => 50,'null' => true]);
            $t->column('identity', 'string', ['limit' => 50,'0' => true]);
            $t->column('identity_used', 'bool', ['limit' => 50,'false' => true]); // how to set a default boolean?
            $t->end();
        }
    }

    /**
     * Downgrade.
     */
    public function down()
    {
        $this->dropTable('imp_smime_extrakeys');
    }
}
