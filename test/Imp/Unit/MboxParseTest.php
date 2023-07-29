<?php
/**
 * Copyright 2011-2017 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file COPYING for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */

/**
 * Test the mbox parsing library.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2011-2016 Horde LLC
 * @ignore
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */
class Imp_Unit_MboxParseTest extends Horde_Test_Case
{
    public function testMboxParse()
    {
        $parse = new IMP_Mbox_Parse(__DIR__ . '/../fixtures/test.mbox');

        $this->assertEquals(
            2,
            count($parse)
        );

        $i = 0;
        foreach ($parse as $key => $val) {
            $this->assertEquals(
                $i++,
                $key
            );

            $this->assertIsArray(
                $val
            );

            $this->assertEquals(
                "Return-Path: <bugs@horde.org>\r\n",
                fgets($val['data'])
            );
        }
    }

    public function testEmlParse()
    {
        $parse = new IMP_Mbox_Parse(__DIR__ . '/../fixtures/test.eml');

        $this->assertEquals(
            0,
            count($parse)
        );

        $val = $parse[0];

        $this->assertIsArray(
            $val
        );

        $this->assertEquals(
            "Return-Path: <bugs@horde.org>\r\n",
            fgets($val['data'])
        );
    }

    public function testBadData()
    {
        $this->expectException('IMP_Exception');

        new IMP_Mbox_Parse(__DIR__ . '/noexist');
    }

}
