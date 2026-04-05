<?php

/**
 * Change sentmail_id column to autoincrement.
 *
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @author   Michael Slusarz <slusarz@horde.org>
 * @category Horde
 * @license  http://www.horde.org/licenses/gpl GPL
 * @package  IMP
 */
class ImpAutoIncrementSentmail extends Horde_Db_Migration_Base
{
    /**
     * Upgrade.
     */
    public function up()
    {
        $this->changeColumn('imp_sentmail', 'sentmail_id', 'autoincrementKey');
        if (in_array('imp_sentmail_seq', $this->tables())) {
            $this->dropTable('imp_sentmail_seq');
        }
    }

    /**
     * Downgrade.
     */
    public function down()
    {
        $this->changeColumn('imp_sentmail', 'sentmail_id', 'bigint', ['autoincrement' => false]);
    }

}
