<?php

namespace WeDevelop\Components\Tests;

use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ArrayData;
use SilverStripe\ORM\FieldType\DBVarchar;
use SilverStripe\View\ViewLayerData;
use stdClass;
use WeDevelop\Components\HtmlAttributes;

class HtmlAttributesTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testRendersEscapedAttributes(): void
    {
        $this->assertSame(
            ' id="main" title="Tom &amp; &quot;Jerry&quot;" data-n="3"',
            HtmlAttributes::render(['id' => 'main', 'title' => 'Tom & "Jerry"', 'data-n' => 3])
        );
    }

    public function testBooleansAndNull(): void
    {
        $this->assertSame(' disabled', HtmlAttributes::render(['disabled' => true, 'hidden' => false, 'x' => null]));
    }

    public function testNonScalarValuesAndInvalidNamesAreSkipped(): void
    {
        $this->assertSame(' a="1"', HtmlAttributes::render(['obj' => new stdClass(), 'a' => '1', 'bad name' => 'x', '"x' => 'y']));
    }

    public function testTemplateValuesRenderTheirRawValueEscapedOnce(): void
    {
        $this->assertSame(
            ' a="x &amp; &quot;y&quot;" b="x &amp; &quot;y&quot;" c="3"',
            HtmlAttributes::render([
                'a' => DBVarchar::create()->setValue('x & "y"'),
                'b' => ViewLayerData::create('x & "y"'),
                'c' => ViewLayerData::create(3),
                'list' => ViewLayerData::create(ArrayData::create(['Title' => 'x'])),
            ])
        );
    }

    public function testEmpty(): void
    {
        $this->assertSame('', HtmlAttributes::render([]));
    }
}
