<?php

namespace WeDevelop\Components\Tests;

use ArrayIterator;
use ArrayObject;
use SilverStripe\Dev\SapphireTest;
use WeDevelop\Components\ComponentRegistry;
use WeDevelop\Components\Exception\DuplicateComponentException;
use WeDevelop\Components\Tests\Fixtures\AlertComponent;

class ComponentRegistryTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testNameIsDerivedFromShortClassName(): void
    {
        $this->assertSame('Alert', ComponentRegistry::nameForClass(AlertComponent::class));
    }

    public function testConfiguredNameWins(): void
    {
        AlertComponent::config()->set('component_name', 'Notice');
        $this->assertSame('Notice', ComponentRegistry::nameForClass(AlertComponent::class));
    }

    public function testFixtureIsRegistered(): void
    {
        $this->assertSame(AlertComponent::class, ComponentRegistry::singleton()->classFor('Alert'));
        $this->assertNull(ComponentRegistry::singleton()->classFor('Nope'));
    }

    public function testSubclassWinsOverParentWithSameName(): void
    {
        $this->assertSame(
            ['List' => \RecursiveArrayIterator::class],
            ComponentRegistry::buildMap([ArrayIterator::class => 'List', \RecursiveArrayIterator::class => 'List'])
        );
        $this->assertSame(
            ['List' => \RecursiveArrayIterator::class],
            ComponentRegistry::buildMap([\RecursiveArrayIterator::class => 'List', ArrayIterator::class => 'List'])
        );
    }

    public function testUnrelatedDuplicatesThrow(): void
    {
        $this->expectException(DuplicateComponentException::class);
        $this->expectExceptionMessage('Component name "X" is used by both ArrayObject and ArrayIterator');
        ComponentRegistry::buildMap([ArrayObject::class => 'X', ArrayIterator::class => 'X']);
    }
}
