<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category   Horde
 * @copyright 2010-2017 Horde LLC
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */

/**
 * Test the Sime-class lib/Basic/Smime.
 *
 * @author     Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */
class Imp_Unit_BasicSmimeTest extends TestCase
{
    protected function setUp(): void
    {
        $this->basicSmime = $this->getBasicSmime();
    }

    public function testIfLibSmimeSetup(){
        $this->assertIsObject($this->basicSmime);
    }
}
