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
 * Test the Sime-class lib/Smime.
 *
 * @author     Rafael te Boekhorst <boekhorst@b1-systems.de>
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */
class Imp_Unit_LibSmimeTest extends TestCase
{
    protected function setUp(): void
    {
        $this->libSmime = $this->getLibSmimeClass();
        $this->p12cert = $this->getKeys('user1');
    }

    /**
     * Testing if the arguments are correctly given
     */
    public function testArgumentsOfAddPersonalPublicKey(){
        $libSmime = $this->libSmime;
        $certFile = $this->p12cert;
        $libSmime->expects($this->once())->method('addPersonalPublicKey');
        $libSmime->addPersonalPublicKey($certFile);
    }
}
