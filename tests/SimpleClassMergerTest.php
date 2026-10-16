<?php

namespace WeDevelop\Components\Tests;

use PHPUnit\Framework\TestCase;
use WeDevelop\Components\SimpleClassMerger;

class SimpleClassMergerTest extends TestCase
{
    public function testJoinsClassesInOrder(): void
    {
        $this->assertSame(
            'btn btn-primary btn-lg',
            (new SimpleClassMerger())->merge('btn btn-primary', 'btn-lg')
        );
    }

    public function testRemovesDuplicatesKeepingFirstOccurrence(): void
    {
        $this->assertSame(
            'card card--featured card--compact',
            (new SimpleClassMerger())->merge('card card--featured', 'card  card--compact')
        );
    }

    public function testDoesNotResolveConflicts(): void
    {
        $this->assertSame('p-3 mb-2 p-1', (new SimpleClassMerger())->merge('p-3 mb-2', 'p-1'));
    }

    public function testEmptyAndNullAreIgnored(): void
    {
        $this->assertSame('rounded', (new SimpleClassMerger())->merge(null, '', " \n ", 'rounded'));
        $this->assertSame('', (new SimpleClassMerger())->merge(null, ''));
    }
}
