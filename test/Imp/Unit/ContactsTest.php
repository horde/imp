<?php

declare(strict_types=1);

namespace Horde\Imp\Test\Unit;

use IMP_Contacts;
use IMP_Factory_Contacts;
use Horde_Injector;
use PHPUnit\Framework\TestCase;
use ReflectionClass;
use Serializable;

/**
 * Tests serialization round-trip for IMP_Contacts and the defensive
 * guards in IMP_Factory_Contacts.
 *
 * Regression coverage for https://github.com/horde/imp/issues/75 — a
 * broken __unserialize() left the session-cached IMP_Contacts as a
 * string on every request after the first, crashing message rendering.
 *
 * @coversNothing
 */
class ContactsTest extends TestCase
{
    public function testFreshInstanceRoundTrip(): void
    {
        $contacts = new IMP_Contacts();

        $restored = unserialize(serialize($contacts));

        $this->assertInstanceOf(IMP_Contacts::class, $restored);
    }

    public function testPopulatedInstanceRoundTrip(): void
    {
        $contacts = new IMP_Contacts();
        $this->seedPrivate($contacts, '_fields', ['source-a' => ['email', 'name']]);
        $this->seedPrivate($contacts, '_sources', ['source-a']);

        $restored = unserialize(serialize($contacts));

        $this->assertInstanceOf(IMP_Contacts::class, $restored);
        $this->assertSame(
            ['source-a' => ['email', 'name']],
            $this->readPrivate($restored, '_fields'),
        );
        $this->assertSame(
            ['source-a'],
            $this->readPrivate($restored, '_sources'),
        );
    }

    /**
     * The magic-methods payload shape is the two-element array
     * [$fields, $sources]. __unserialize() must consume it directly
     * — issue #75 was that an array_unshift/array_shift mix-up made
     * this path destructure null.
     */
    public function testMagicUnserializeConsumesPayloadDirectly(): void
    {
        $contacts = new IMP_Contacts();

        $contacts->__unserialize([
            ['source-b' => ['email']],
            ['source-b'],
        ]);

        $this->assertSame(
            ['source-b' => ['email']],
            $this->readPrivate($contacts, '_fields'),
        );
        $this->assertSame(
            ['source-b'],
            $this->readPrivate($contacts, '_sources'),
        );
    }

    public function testSerializableInterfaceIsNotImplemented(): void
    {
        /* The legacy Serializable interface + its serialize()/
         * unserialize() methods disagreed with __serialize()/
         * __unserialize() about the payload shape. Dropping it is
         * part of the fix; lock that in. */
        $this->assertNotInstanceOf(
            Serializable::class,
            new IMP_Contacts(),
        );
    }

    public function testFactoryShutdownIsSafeWithoutCreate(): void
    {
        /* Horde_Shutdown fires shutdown() unconditionally. If create()
         * never populated _instance (or a future regression leaves it
         * as a non-object) the handler must not blow up. */
        $factory = new IMP_Factory_Contacts(
            $this->createStub(Horde_Injector::class),
        );

        $factory->shutdown();

        $this->expectNotToPerformAssertions();
    }

    private function seedPrivate(object $object, string $property, mixed $value): void
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        $prop->setValue($object, $value);
    }

    private function readPrivate(object $object, string $property): mixed
    {
        $ref = new ReflectionClass($object);
        $prop = $ref->getProperty($property);
        $prop->setAccessible(true);
        return $prop->getValue($object);
    }
}
