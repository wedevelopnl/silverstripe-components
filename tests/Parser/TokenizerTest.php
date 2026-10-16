<?php

namespace WeDevelop\Components\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use WeDevelop\Components\Parser\ComponentNode;
use WeDevelop\Components\Parser\Tokenizer;
use WeDevelop\Components\Parser\TokenizerException;

class TokenizerTest extends TestCase
{
    private function tokenize(string $source): array
    {
        return (new Tokenizer())->tokenize($source);
    }

    private function single(string $source): ComponentNode
    {
        $nodes = $this->tokenize($source);
        $this->assertCount(1, $nodes);
        return $nodes[0];
    }

    private function attrs(ComponentNode $node): array
    {
        $out = [];
        foreach ($node->attributes as $attr) {
            $out[$attr->name] = [$attr->value, $attr->quoted];
        }
        return $out;
    }

    public function testPlainTemplateHasNoNodes(): void
    {
        $this->assertSame([], $this->tokenize('<p>$Title</p><% if $A > 1 %>x<% end_if %>'));
    }

    public function testLowercaseAndNamespacedTagsAreIgnored(): void
    {
        $this->assertSame([], $this->tokenize('<:foo>a</:foo> <x:Bar/> a <: b'));
    }

    public function testSelfClosingComponentWithAttributes(): void
    {
        $source = 'before <:Button label="Save" item=$Page.Link n=3 disabled /> after';
        $node = $this->single($source);

        $this->assertSame('Button', $node->name);
        $this->assertSame([
            'label' => ['Save', true],
            'item' => ['$Page.Link', false],
            'n' => ['3', false],
            'disabled' => [null, false],
        ], $this->attrs($node));
        $this->assertSame('', $node->defaultBody);
        $this->assertSame([], $node->slots);
        $this->assertSame('<:Button label="Save" item=$Page.Link n=3 disabled />', substr($source, $node->offset, $node->length));
    }

    public function testSelfClosingEqualsEmptyPair(): void
    {
        $a = $this->single('<:Button label="x" />');
        $b = $this->single('<:Button label="x"></:Button>');
        $this->assertEquals($this->attrs($a), $this->attrs($b));
        $this->assertSame($a->defaultBody, $b->defaultBody);
        $this->assertSame($a->slots, $b->slots);
    }

    public function testQuotedValuesMayContainTagCharacters(): void
    {
        $node = $this->single("<:Card title=\"a > b /> c\" alt='say \"hi\"'>x</:Card>");
        $this->assertSame([
            'title' => ['a > b /> c', true],
            'alt' => ['say "hi"', true],
        ], $this->attrs($node));
        $this->assertSame('x', $node->defaultBody);
    }

    public function testUnquotedValueWithMethodArguments(): void
    {
        $node = $this->single('<:List items=$Page.Children(1, "a b") />');
        $this->assertSame(['items' => ['$Page.Children(1, "a b")', false]], $this->attrs($node));
    }

    public function testDottedNames(): void
    {
        $this->assertSame('Form.Input', $this->single('<:Form.Input name="q" />')->name);
    }

    public function testBodyMayContainTemplateTags(): void
    {
        $node = $this->single('<:Card><% if $A > 1 %>big<% end_if %></:Card>');
        $this->assertSame('<% if $A > 1 %>big<% end_if %>', $node->defaultBody);
    }

    public function testNamedSlotsAreSplitFromDefaultBody(): void
    {
        $node = $this->single('<:Card>Hello <:slot name="footer"><a href="$Link">x</a></:slot> world</:Card>');
        $this->assertSame('Hello  world', $node->defaultBody);
        $this->assertSame(['footer' => '<a href="$Link">x</a>'], $node->slots);
    }

    public function testNestedComponentsAndTheirSlotsBelongToTheInnerComponent(): void
    {
        $source = '<:Card>A<:Card>B<:slot name="footer">inner</:slot></:Card><:slot name="footer">outer</:slot></:Card>';
        $node = $this->single($source);
        $this->assertSame('A<:Card>B<:slot name="footer">inner</:slot></:Card>', $node->defaultBody);
        $this->assertSame(['footer' => 'outer'], $node->slots);
        $this->assertSame(strlen($source), $node->length);
    }

    public function testMultipleTopLevelNodes(): void
    {
        $nodes = $this->tokenize('<:A /> mid <:B>x</:B>');
        $this->assertSame(['A', 'B'], array_map(fn (ComponentNode $n) => $n->name, $nodes));
        $this->assertSame(11, $nodes[1]->offset);
    }

    public function testCommentedOutCallsAreIgnored(): void
    {
        $this->assertSame([], $this->tokenize("a<%-- <:Panel>\nx\n</:Panel> --%>b"));
        $nodes = $this->tokenize('<%-- <:A /> --%><:B />');
        $this->assertSame(['B'], array_map(fn (ComponentNode $n) => $n->name, $nodes));
        $this->assertSame(16, $nodes[0]->offset);
    }

    public function testMalformedCallInsideCommentDoesNotThrow(): void
    {
        $this->assertSame([], $this->tokenize('<%-- <:Card title="x </:Panel> <:slot> --%>'));
    }

    public function testCommentInsideBodyKeepsItsTextAndHidesItsTags(): void
    {
        $node = $this->single('<:Card>A<%-- <:slot name="footer">x</:slot> <:Panel> --%>B<:slot name="s">y</:slot></:Card>');
        $this->assertSame('A<%-- <:slot name="footer">x</:slot> <:Panel> --%>B', $node->defaultBody);
        $this->assertSame(['s' => 'y'], $node->slots);
    }

    /**
     * @return array<string, array{string, string, int}>
     */
    public static function errorProvider(): array
    {
        return [
            'unclosed component' => ['ab<:Card>x', 'Unclosed component <:Card>', 2],
            'mismatched close' => ['<:Card>x</:Panel>', 'Expected </:Card> but found </:Panel>', 8],
            'stray close' => ['x</:Card>', 'Unexpected closing tag </:Card>', 1],
            'slot outside component' => ['<:slot name="a">x</:slot>', '<:slot> can only be used directly inside a component', 0],
            'stray slot close' => ['<:Card></:slot></:Card>', 'Unexpected </:slot>', 7],
            'nested slot' => ['<:Card><:slot name="a"><:slot name="b"></:slot></:slot></:Card>', 'Slots cannot be nested', 23],
            'duplicate slot' => ['<:Card><:slot name="a"></:slot><:slot name="a"></:slot></:Card>', 'Duplicate slot "a"', 31],
            'slot without name' => ['<:Card><:slot>x</:slot></:Card>', '<:slot> requires a quoted name attribute', 7],
            'default slot name' => ['<:Card><:slot name="default">x</:slot></:Card>', 'Slot name "default" is reserved', 7],
            'self-closing slot' => ['<:Card><:slot name="a" /></:Card>', '<:slot> must have a closing tag', 7],
            'unclosed slot' => ['<:Card><:slot name="a">x</:Card>', 'Unclosed <:slot name="a">', 7],
            'template tag in attribute' => ['<:Card title="<% if $A %>x<% end_if %>" />', 'Template tags are not allowed inside component attributes', 0],
            'duplicate attribute' => ['<:Card a="1" a="2" />', 'Duplicate attribute "a"', 13],
            'invalid attribute' => ['<:Card "x" />', 'Invalid attribute syntax', 7],
            'unterminated tag' => ['<:Card title="x"', 'Unterminated tag <:Card>', 0],
            'unterminated quote' => ['<:Card title="x />', 'Unterminated quoted value', 13],
            'slot inside if' => ['<:Card><% if $A %><:slot name="f">x</:slot><% end_if %></:Card>', 'put the condition inside the slot', 18],
            'slot inside loop' => ['<:Card><% loop $A %><:slot name="f">x</:slot><% end_loop %></:Card>', '<% loop %>', 20],
            'slot inside else' => ['<:Card><% if $A %>a<% else %><:slot name="f">x</:slot><% end_if %></:Card>', '<% if %>', 29],
        ];
    }

    public function testSlotAfterClosedBlockOrInsideCommentedBlockIsAllowed(): void
    {
        $nodes = $this->tokenize(
            '<:Card><% if $A %>a<% end_if %><%-- <% if $B %> --%><% with $C %>c<% end_with %>'
            . '<:slot name="f"><% if $D %>d<% end_if %></:slot></:Card>'
        );
        $this->assertSame(['f' => '<% if $D %>d<% end_if %>'], $nodes[0]->slots);
    }

    public function testBlocksInsideNestedComponentsDoNotAffectOuterSlots(): void
    {
        $nodes = $this->tokenize('<:Card><:Inner><% if $A %><:slot name="x">y</:slot><% end_if %></:Inner><:slot name="f">z</:slot></:Card>');
        $this->assertSame(['f' => 'z'], $nodes[0]->slots);
    }

    #[DataProvider('errorProvider')]
    public function testErrors(string $source, string $message, int $offset): void
    {
        try {
            $this->tokenize($source);
            $this->fail('Expected TokenizerException');
        } catch (TokenizerException $e) {
            $this->assertStringContainsString($message, $e->getMessage());
            $this->assertSame($offset, $e->offset);
        }
    }
}
