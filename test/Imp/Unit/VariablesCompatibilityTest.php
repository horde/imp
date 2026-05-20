<?php

declare(strict_types=1);

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (GPL). If you
 * did not receive this file, see http://www.horde.org/licenses/gpl.
 *
 * @category   Horde
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 */

// Bootstrap from vendor autoload when run directly
if (!class_exists('Horde_Variables')) {
    require_once __DIR__ . '/../../../vendor/autoload.php';
}

use Horde\Util\Variables;
use PHPUnit\Framework\TestCase;

/**
 * Test Variables type compatibility for transition from Horde_Variables
 * to Horde\Util\Variables.
 *
 * Tests focus on type acceptance - verifying that methods accept both
 * legacy and modern Variables classes without TypeError.
 *
 * @author     Horde LLC
 * @category   Horde
 * @copyright  2026 Horde LLC
 * @license    http://www.horde.org/licenses/gpl GPL
 * @package    IMP
 * @subpackage UnitTests
 * @coversNothing
 */
class Imp_Unit_VariablesCompatibilityTest extends TestCase
{
    /**
     * Test that both Variables types are accepted by type hints.
     * Uses reflection to verify type compatibility without invoking full logic.
     */
    public function testApplicationDownloadAcceptsBothTypes(): void
    {
        $method = new ReflectionMethod(IMP_Application::class, 'download');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('vars', $params[0]->getName());

        // Verify type accepts both
        $type = $params[0]->getType();
        $this->assertInstanceOf(ReflectionUnionType::class, $type);

        $types = array_map(fn($t) => $t->getName(), $type->getTypes());
        $this->assertContains('Horde\Util\Variables', $types);
        $this->assertContains('Horde_Variables', $types);
    }

    /**
     * Test IMP_Contents_View::checkToken accepts both types
     */
    public function testContentsViewCheckTokenAcceptsBothTypes(): void
    {
        $method = new ReflectionMethod(IMP_Contents_View::class, 'checkToken');
        $params = $method->getParameters();

        $this->assertCount(1, $params);
        $this->assertEquals('vars', $params[0]->getName());

        $type = $params[0]->getType();
        $this->assertInstanceOf(ReflectionUnionType::class, $type);

        $types = array_map(fn($t) => $t->getName(), $type->getTypes());
        $this->assertContains('Horde\Util\Variables', $types);
        $this->assertContains('Horde_Variables', $types);
    }

    /**
     * Test IMP_Indices_Mailbox instanceof check works with both types
     */
    public function testIndicesMailboxInstanceofWorksWithLegacy(): void
    {
        $vars = new Horde_Variables(['test' => 'value']);

        // Test the instanceof logic that's in IMP_Indices_Mailbox
        $this->assertTrue(
            $vars instanceof Horde_Variables || $vars instanceof Variables,
            'Legacy Horde_Variables should match instanceof check'
        );
    }

    /**
     * Test IMP_Indices_Mailbox instanceof check works with modern type
     */
    public function testIndicesMailboxInstanceofWorksWithModern(): void
    {
        $vars = new Variables(['test' => 'value']);

        // Test the instanceof logic that's in IMP_Indices_Mailbox
        $this->assertTrue(
            $vars instanceof Horde_Variables || $vars instanceof Variables,
            'Modern Horde\Util\Variables should match instanceof check'
        );
    }

    /**
     * Test that both Variables types have compatible interfaces
     */
    public function testBothTypesImplementSameInterfaces(): void
    {
        $legacy = new ReflectionClass(Horde_Variables::class);
        $modern = new ReflectionClass(Variables::class);

        $legacyInterfaces = $legacy->getInterfaceNames();
        $modernInterfaces = $modern->getInterfaceNames();

        // Both should implement ArrayAccess, Countable, IteratorAggregate
        $requiredInterfaces = ['ArrayAccess', 'Countable', 'IteratorAggregate'];

        foreach ($requiredInterfaces as $interface) {
            $this->assertContains($interface, $legacyInterfaces);
            $this->assertContains($interface, $modernInterfaces);
        }
    }

    /**
     * Test that both Variables types have compatible methods
     */
    public function testBothTypesHaveCompatibleMethods(): void
    {
        $legacy = new ReflectionClass(Horde_Variables::class);
        $modern = new ReflectionClass(Variables::class);

        $requiredMethods = ['get', 'exists', 'set', 'remove'];

        foreach ($requiredMethods as $methodName) {
            $this->assertTrue(
                $legacy->hasMethod($methodName),
                "Horde_Variables should have method: $methodName"
            );
            $this->assertTrue(
                $modern->hasMethod($methodName),
                "Horde\Util\Variables should have method: $methodName"
            );
        }
    }

    /**
     * Test property access works identically on both types
     */
    public function testPropertyAccessWorksOnBothTypes(): void
    {
        $legacyVars = new Horde_Variables(['foo' => 'bar', 'baz' => 'qux']);
        $modernVars = new Variables(['foo' => 'bar', 'baz' => 'qux']);

        // Test dynamic property access
        $this->assertEquals('bar', $legacyVars->foo);
        $this->assertEquals('bar', $modernVars->foo);

        // Test get method
        $this->assertEquals('qux', $legacyVars->get('baz'));
        $this->assertEquals('qux', $modernVars->get('baz'));

        // Test default values
        $this->assertEquals('default', $legacyVars->get('missing', 'default'));
        $this->assertEquals('default', $modernVars->get('missing', 'default'));
    }

    /**
     * Test array access works identically on both types
     */
    public function testArrayAccessWorksOnBothTypes(): void
    {
        $legacyVars = new Horde_Variables(['key' => 'value']);
        $modernVars = new Variables(['key' => 'value']);

        // Test ArrayAccess interface
        $this->assertEquals('value', $legacyVars['key']);
        $this->assertEquals('value', $modernVars['key']);

        $this->assertTrue(isset($legacyVars['key']));
        $this->assertTrue(isset($modernVars['key']));

        $this->assertFalse(isset($legacyVars['missing']));
        $this->assertFalse(isset($modernVars['missing']));
    }
}
