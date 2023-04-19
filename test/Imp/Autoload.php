<?php
/**
 * Setup autoloading for the tests.
 *
 * @author     Gunnar Wrobel <wrobel@pardus.de>
 * @category   Horde
 * @ignore
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */

// this should load all classes in the lib/ directory into autoload
Horde_Test_Autoload::addPrefix('IMP', __DIR__ . '/../../lib');
Horde_Test_Autoload::init();
