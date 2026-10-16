<?php

namespace WeDevelop\Components\Tests;

use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use WeDevelop\Components\ClassMerger;
use WeDevelop\Components\TailwindClassMerger;

class TailwindClassMergerTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function merger(): ClassMerger
    {
        return Injector::inst()->get(ClassMerger::class);
    }

    public function testIsTheDefaultMerger(): void
    {
        $this->assertInstanceOf(TailwindClassMerger::class, $this->merger());
    }

    public function testLaterClassesWinConflicts(): void
    {
        $this->assertSame('py-2 px-8', $this->merger()->merge('px-4 py-2', 'px-8'));
    }

    public function testCustomThemeColoursConflict(): void
    {
        $this->assertSame(
            'text-white hover:bg-brand-700 bg-red-500',
            $this->merger()->merge('bg-brand-600 text-white hover:bg-brand-700', 'bg-red-500')
        );
    }

    public function testEmptyAndNullAreIgnored(): void
    {
        $this->assertSame('rounded', $this->merger()->merge(null, '', '  ', 'rounded'));
        $this->assertSame('', $this->merger()->merge(null, ''));
    }

    public function testWorksWithoutCache(): void
    {
        $this->assertSame('py-2 px-8', (new TailwindClassMerger())->merge('px-4 py-2', 'px-8'));
    }
}
