<?php

namespace WeDevelop\Components\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\Model\ArrayData;
use SilverStripe\Model\List\ArrayList;
use SilverStripe\TemplateEngine\Exception\SSTemplateParseException;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\TemplateEngine\SSTemplateParser;
use SilverStripe\View\ViewLayerData;
use WeDevelop\Components\Exception\ComponentNotFoundException;
use WeDevelop\Components\Exception\InvalidComponentPropException;
use WeDevelop\Components\Parser\ComponentParser;

class ComponentParserTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function render(string $template, array $data = []): string
    {
        $html = SSTemplateEngine::create()->renderString(
            $template,
            ViewLayerData::create(ArrayData::create($data)),
            [],
            false
        );
        return preg_replace(['/>\s+</', '/\s+/'], ['><', ' '], trim($html));
    }

    public function testParserIsSwappedInViaInjector(): void
    {
        $this->assertInstanceOf(ComponentParser::class, Injector::inst()->get(SSTemplateParser::class));
    }

    public function testStringProp(): void
    {
        $this->assertSame('<div class="p-2" title="Hello">[Hello][][]</div>', $this->render('<:Plain title="Hello" />'));
    }

    public function testSelfClosingEqualsEmptyPair(): void
    {
        $this->assertSame($this->render('<:Plain title="x" />'), $this->render('<:Plain title="x"></:Plain>'));
    }

    public function testInterpolatedPropIsEscapedOnce(): void
    {
        $this->assertSame(
            '<div class="p-2" title="Hi Tom &amp; Jerry">[Hi Tom &amp; Jerry][][]</div>',
            $this->render('<:Plain title="Hi {$Name}" />', ['Name' => 'Tom & Jerry'])
        );
    }

    public function testParentScopeDoesNotLeak(): void
    {
        $this->assertSame('<div class="p-2">[][][]</div>', $this->render('<:Plain />', ['Name' => 'X']));
    }

    public function testObjectProp(): void
    {
        $items = ArrayList::create([ArrayData::create(['Title' => 'A']), ArrayData::create(['Title' => 'B'])]);
        $this->assertSame('<ul><li>A</li><li>B</li></ul>', $this->render('<:Listing items=$Items />', ['Items' => $items]));
    }

    public function testBooleanAttribute(): void
    {
        $this->assertSame('<div class="p-2" hidden>[][][]</div>', $this->render('<:Plain hidden />'));
    }

    public function testDefaultSlotRendersInCallerScopeEscapedOnce(): void
    {
        $this->assertSame(
            '<div class="p-2">[][Hi <b>&lt;i&gt;</b>][]</div>',
            $this->render('<:Plain>Hi <b>$Name</b></:Plain>', ['Name' => '<i>'])
        );
    }

    public function testNamedSlotRendersInCallerScope(): void
    {
        $this->assertSame(
            '<section>[Body]<footer><a href="/x">Mick</a></footer></section>',
            $this->render(
                '<:Panel>Body<:slot name="footer"><a href="$Link">$Name</a></:slot></:Panel>',
                ['Link' => '/x', 'Name' => 'Mick']
            )
        );
    }

    public function testTopInSlotIsTheCallerPage(): void
    {
        $this->assertSame(
            '<section>[]<footer>Page</footer></section>',
            $this->render('<:Panel><:slot name="footer">$Top.Title</:slot></:Panel>', ['Title' => 'Page'])
        );
    }

    public function testMissingSlotIsNotRendered(): void
    {
        $this->assertSame('<section>[Body]</section>', $this->render('<:Panel>Body</:Panel>'));
    }

    public function testNamedSlotWithEmptyConditionalContentIsNotRendered(): void
    {
        $template = '<:Panel>Body<:slot name="footer"> <% if $A %>f<% end_if %> </:slot></:Panel>';
        $this->assertSame('<section>[Body]</section>', $this->render($template, ['A' => false]));
        $this->assertSame('<section>[Body]<footer> f </footer></section>', $this->render($template, ['A' => true]));
    }

    public function testSlotInsideIfBlockIsAParseError(): void
    {
        $this->expectException(SSTemplateParseException::class);
        $this->expectExceptionMessage('put the condition inside the slot');
        $this->render('<:Panel><% if $A %><:slot name="footer">f</:slot><% end_if %></:Panel>');
    }

    public function testWhitespaceOnlyBodyIsNoChildren(): void
    {
        $this->assertSame('<section>[]</section>', $this->render("<:Panel> \n </:Panel>"));
    }

    public function testSlotsInsideLoopSeeCurrentItem(): void
    {
        $items = ArrayList::create([ArrayData::create(['Title' => 'A']), ArrayData::create(['Title' => 'B'])]);
        $this->assertSame(
            '<section>[A]<footer>1</footer></section><section>[B]<footer>2</footer></section>',
            $this->render(
                '<% loop $Items %><:Panel>$Title<:slot name="footer">$Pos</:slot></:Panel><% end_loop %>',
                ['Items' => $items]
            )
        );
    }

    public function testNestedComponents(): void
    {
        $this->assertSame(
            '<section>[<span class="badge">Mick</span>]</section>',
            $this->render('<:Panel><:Ui.Badge label="$Name" /></:Panel>', ['Name' => 'Mick'])
        );
    }

    public function testClassComponentMergesCallerClass(): void
    {
        $this->assertSame(
            '<div class="rounded bg-red-50 text-red-900 p-8" id="msg" role="alert">Saved</div>',
            $this->render('<:Alert message="Saved" tone="danger" class="p-8" id="msg" />')
        );
    }

    public function testUnquotedVariableOnPassthroughAttributeRendersEscapedOnce(): void
    {
        $this->assertSame(
            '<div class="p-2" data-id="a&quot;b&amp;c" aria-label="a&quot;b&amp;c">[][][]</div>',
            $this->render('<:Plain data-id=$Name aria-label="$Name" />', ['Name' => 'a"b&c'])
        );
    }

    public function testUnquotedClassMergesAndIsEscapedOnce(): void
    {
        $this->assertSame(
            '<div class="rounded bg-blue-50 text-blue-900 p-8 a&amp;b&quot;" role="alert">m</div>',
            $this->render('<:Alert message="m" class=$Cls />', ['Cls' => 'p-8 a&b"'])
        );
    }

    public function testUnquotedVariantValueIsComparedRaw(): void
    {
        $this->assertSame(
            '<div class="rounded p-4 bg-blue-50 text-blue-900 shadow" role="alert">m</div>',
            $this->render('<:Alert message="m" shape=$Shape />', ['Shape' => 'a&b'])
        );
    }

    public function testComponentInsideCachedBlock(): void
    {
        $this->assertSame(
            '<span class="badge">Mick</span>',
            $this->render('<% cached "component-test", $Name %><:Ui.Badge label="$Name" /><% end_cached %>', ['Name' => 'Mick'])
        );
    }

    public function testCommentedOutMultiLineCallIsIgnored(): void
    {
        $this->assertSame('ab', $this->render("a<%-- <:Panel>\nx\n</:Panel> --%>b"));
    }

    public function testInvalidPropThrows(): void
    {
        $this->expectException(InvalidComponentPropException::class);
        $this->render('<:Alert message="x" tone="nope" />');
    }

    public function testUnknownComponentThrows(): void
    {
        $this->expectException(ComponentNotFoundException::class);
        $this->render('<:Nope />');
    }

    public function testTokenizerErrorsReportLine(): void
    {
        $this->expectException(SSTemplateParseException::class);
        $this->expectExceptionMessage('line 2');
        $this->render("<p>\n<:Panel>never closed");
    }

    public function testLineNumbersAfterMultiLineComponentArePreserved(): void
    {
        $this->expectException(SSTemplateParseException::class);
        $this->expectExceptionMessage('line 4');
        $this->render("<:Panel>\na\n</:Panel>\n<% end_if %>");
    }

    /**
     * @return array<string, array{string, int}>
     */
    public static function nestedErrorLineProvider(): array
    {
        return [
            'stray end_if in named slot' => ["<p>\n<:Panel>\n<:slot name=\"footer\">\nx\n<% end_if %>\n</:slot>\n</:Panel>", 5],
            'stray end_if in default body after multi-line slot' => ["<:Panel>\n<:slot name=\"footer\">\nx\n</:slot>\n<% end_if %>\n</:Panel>", 5],
            'invalid unquoted attribute value' => ["<p>\n\n<:Plain\n  title=\$Foo..Bar\n/>", 4],
            'error in nested component in slot' => ["<:Panel>\n<:slot name=\"footer\">\n<:Panel>\na\n<% end_loop %>\n</:Panel>\n</:slot>\n</:Panel>", 5],
            'tokenizer error in nested component in slot' => ["<:Panel>\n<:slot name=\"footer\">\n<:Panel>\n<% if \$A %>\n<:slot name=\"x\">y</:slot>\n<% end_if %>\n</:Panel>\n</:slot>\n</:Panel>", 5],
        ];
    }

    #[DataProvider('nestedErrorLineProvider')]
    public function testErrorsInsideCallsReportTheTemplateLineAndName(string $template, int $line): void
    {
        try {
            Injector::inst()->create(SSTemplateParser::class)->compileString($template, 'Layout/Page.ss');
            $this->fail('Expected SSTemplateParseException');
        } catch (SSTemplateParseException $e) {
            $this->assertStringContainsString("Layout/Page.ss on line {$line}.", $e->getMessage());
        }
    }

    public function testErrorsInsideSlotBodiesAreReported(): void
    {
        $this->expectException(SSTemplateParseException::class);
        $this->render('<:Panel><:slot name="footer"><:Card></:slot></:Panel>');
    }
}
