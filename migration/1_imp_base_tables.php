<?php
/**
 * Create IMP base tables (as of IMP 4.3).
 *
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  IMP
 */
class ImpBaseTables extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        // Create: imp_sentmail
        $tableList = $this->tables();
        if (!in_array('imp_sentmail', $tableList)) {
            $t = $this->createTable('imp_sentmail', ['autoincrementKey' => false]);
            $t->column('sentmail_id', 'bigint', ['null' => false]);
            $t->column('sentmail_who', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sentmail_ts', 'bigint', ['null' => false]);
            $t->column('sentmail_messageid', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sentmail_action', 'string', ['limit' => 32, 'null' => false]);
            $t->column('sentmail_recipient', 'string', ['limit' => 255, 'null' => false]);
            $t->column('sentmail_success', 'integer', ['null' => false]);
            $t->primaryKey(['sentmail_id']);
            $t->end();

            $this->addIndex('imp_sentmail', ['sentmail_ts']);
            $this->addIndex('imp_sentmail', ['sentmail_who']);
            $this->addIndex('imp_sentmail', ['sentmail_success']);
        }
    }

    /**
     * Downgrade.
     */
    public function down()
    {
        $this->dropTable('imp_sentmail');
    }
}
