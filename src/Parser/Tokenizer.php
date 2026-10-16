<?php

namespace WeDevelop\Components\Parser;

/**
 * Finds top-level <:Component> calls in a template source and splits their bodies into
 * a default body and named slots. Nested components are left in the bodies untouched;
 * they are tokenized again when the body itself is compiled.
 */
class Tokenizer
{
    private const TAG_START = '/<(\/?):(slot\b|[A-Z][A-Za-z0-9]*(?:\.[A-Z][A-Za-z0-9]*)*)/';
    private const ATTRIBUTE_NAME = '/\G[A-Za-z_@][A-Za-z0-9_\-:.@]*/';
    private const SLOT_NAME = '/^[A-Za-z][A-Za-z0-9_-]*$/';
    private const BLOCK_TAG = '/<%\s*(end_)?(if|unless|loop|with|cached|uncached|cacheblock)\b/';

    private string $source = '';

    /**
     * @return list<ComponentNode>
     */
    public function tokenize(string $source): array
    {
        $this->source = $source;
        $nodes = [];
        $pos = 0;

        while ($tag = $this->nextTag($pos)) {
            if ($tag->kind === Tag::OPEN) {
                $node = $this->parseComponent($tag);
                $nodes[] = $node;
                $pos = $node->offset + $node->length;
                continue;
            }
            if ($tag->kind === Tag::SLOT_OPEN) {
                throw new TokenizerException('<:slot> can only be used directly inside a component', $tag->start);
            }
            if ($tag->kind === Tag::SLOT_CLOSE) {
                throw new TokenizerException('Unexpected </:slot>', $tag->start);
            }
            throw new TokenizerException("Unexpected closing tag </:{$tag->name}>", $tag->start);
        }

        return $nodes;
    }

    private function parseComponent(Tag $open): ComponentNode
    {
        if ($open->selfClosing) {
            return new ComponentNode($open->name, $open->attributes, '', [], $open->start, $open->end - $open->start);
        }

        $default = '';
        $slots = [];
        $slotOffsets = [];
        $segmentStart = $open->end;
        $slotName = null;
        $slotStart = 0;
        $slotTagStart = 0;
        $stack = [];
        $blocks = [];
        $pos = $open->end;

        while (true) {
            $tag = $this->nextTag($pos);
            if (!$tag) {
                throw new TokenizerException("Unclosed component <:{$open->name}>", $open->start);
            }
            if (!$stack && $slotName === null) {
                $blocks = $this->trackBlocks($blocks, substr($this->source, $pos, $tag->start - $pos));
            }
            $pos = $tag->end;

            switch ($tag->kind) {
                case Tag::OPEN:
                    if (!$tag->selfClosing) {
                        $stack[] = $tag->name;
                    }
                    break;

                case Tag::CLOSE:
                    if ($stack) {
                        $expected = array_pop($stack);
                        if ($expected !== $tag->name) {
                            throw new TokenizerException("Expected </:{$expected}> but found </:{$tag->name}>", $tag->start);
                        }
                        break;
                    }
                    if ($tag->name !== $open->name) {
                        throw new TokenizerException("Expected </:{$open->name}> but found </:{$tag->name}>", $tag->start);
                    }
                    if ($slotName !== null) {
                        throw new TokenizerException("Unclosed <:slot name=\"{$slotName}\">", $slotTagStart);
                    }
                    $default .= substr($this->source, $segmentStart, $tag->start - $segmentStart);
                    return new ComponentNode(
                        $open->name,
                        $open->attributes,
                        $default,
                        $slots,
                        $open->start,
                        $tag->end - $open->start,
                        $open->end,
                        $slotOffsets
                    );

                case Tag::SLOT_OPEN:
                    if ($stack) {
                        break;
                    }
                    if ($slotName !== null) {
                        throw new TokenizerException('Slots cannot be nested', $tag->start);
                    }
                    $name = $this->slotName($tag);
                    if ($blocks) {
                        $block = end($blocks);
                        throw new TokenizerException(
                            "<:slot name=\"{$name}\"> cannot be inside <% {$block} %>; put the condition inside the slot: "
                            . "<:slot name=\"{$name}\"><% {$block} … %>…<% end_{$block} %></:slot>",
                            $tag->start
                        );
                    }
                    if (array_key_exists($name, $slots)) {
                        throw new TokenizerException("Duplicate slot \"{$name}\"", $tag->start);
                    }
                    $default .= substr($this->source, $segmentStart, $tag->start - $segmentStart);
                    $slotName = $name;
                    $slotStart = $tag->end;
                    $slotTagStart = $tag->start;
                    break;

                case Tag::SLOT_CLOSE:
                    if ($stack) {
                        break;
                    }
                    if ($slotName === null) {
                        throw new TokenizerException('Unexpected </:slot>', $tag->start);
                    }
                    $slots[$slotName] = substr($this->source, $slotStart, $tag->start - $slotStart);
                    $slotOffsets[$slotName] = $slotStart;
                    $newlines = preg_match_all('/\r\n|\r|\n/', substr($this->source, $slotTagStart, $tag->end - $slotTagStart));
                    if ($newlines > 0) {
                        // Keep the slot's line breaks in the default body (as a comment, so nothing renders)
                        $default .= '<%--' . str_repeat("\n", $newlines) . '--%>';
                    }
                    $slotName = null;
                    $segmentStart = $tag->end;
                    break;
            }
        }
    }

    /**
     * Updates the stack of open template blocks (<% if %>, <% loop %>, …) with the block tags
     * in a piece of component body. Template comments are ignored; unbalanced end tags are left
     * for the template parser to report.
     *
     * @param list<string> $blocks
     * @return list<string>
     */
    private function trackBlocks(array $blocks, string $text): array
    {
        $text = preg_replace('/<%--.*?(?:--%>|$)/s', '', $text);
        preg_match_all(self::BLOCK_TAG, $text, $matches, PREG_SET_ORDER);
        foreach ($matches as $match) {
            if ($match[1] === '') {
                $blocks[] = $match[2];
            } elseif ($blocks && end($blocks) === $match[2]) {
                array_pop($blocks);
            }
        }
        return $blocks;
    }

    private function slotName(Tag $tag): string
    {
        if ($tag->selfClosing) {
            throw new TokenizerException('<:slot> must have a closing tag', $tag->start);
        }
        foreach ($tag->attributes as $attribute) {
            if ($attribute->name === 'name' && $attribute->quoted && preg_match(self::SLOT_NAME, (string) $attribute->value)) {
                if ($attribute->value === 'default') {
                    throw new TokenizerException('Slot name "default" is reserved; put default content outside of <:slot>', $tag->start);
                }
                return $attribute->value;
            }
        }
        throw new TokenizerException('<:slot> requires a quoted name attribute, e.g. <:slot name="footer">', $tag->start);
    }

    private function nextTag(int $from): ?Tag
    {
        // Skip template comments (<%-- … --%>): tags inside them are not calls, and their text
        // stays part of whatever surrounds them. An unterminated comment runs to the end.
        while (true) {
            if (!preg_match(self::TAG_START, $this->source, $match, PREG_OFFSET_CAPTURE, $from)) {
                return null;
            }
            $start = $match[0][1];
            $comment = strpos($this->source, '<%--', $from);
            if ($comment === false || $comment > $start) {
                break;
            }
            $commentEnd = strpos($this->source, '--%>', $comment + 4);
            if ($commentEnd === false) {
                return null;
            }
            $from = $commentEnd + 4;
        }
        $closing = $match[1][0] === '/';
        $name = $match[2][0];
        $isSlot = $name === 'slot';
        $pos = $start + strlen($match[0][0]);

        if ($closing) {
            if (!preg_match('/\G\s*>/', $this->source, $end, 0, $pos)) {
                throw new TokenizerException("Unterminated tag </:{$name}>", $start);
            }
            return new Tag($isSlot ? Tag::SLOT_CLOSE : Tag::CLOSE, $name, [], false, $start, $pos + strlen($end[0]));
        }

        [$attributes, $selfClosing, $end] = $this->parseAttributes($name, $start, $pos);
        return new Tag($isSlot ? Tag::SLOT_OPEN : Tag::OPEN, $name, $attributes, $selfClosing, $start, $end);
    }

    /**
     * @return array{list<Attribute>, bool, int} attributes, self-closing, end offset
     */
    private function parseAttributes(string $tagName, int $tagStart, int $pos): array
    {
        $attributes = [];
        $seen = [];
        $length = strlen($this->source);

        while (true) {
            while ($pos < $length && ctype_space($this->source[$pos])) {
                $pos++;
            }
            if ($pos >= $length) {
                throw new TokenizerException("Unterminated tag <:{$tagName}>", $tagStart);
            }
            if (substr($this->source, $pos, 2) === '/>') {
                return [$attributes, true, $pos + 2];
            }
            if ($this->source[$pos] === '>') {
                return [$attributes, false, $pos + 1];
            }
            if (!preg_match(self::ATTRIBUTE_NAME, $this->source, $nameMatch, 0, $pos)) {
                throw new TokenizerException("Invalid attribute syntax in <:{$tagName}>", $pos);
            }
            $attrStart = $pos;
            $name = $nameMatch[0];
            $pos += strlen($name);
            if (isset($seen[$name])) {
                throw new TokenizerException("Duplicate attribute \"{$name}\" on <:{$tagName}>", $attrStart);
            }
            $seen[$name] = true;

            if (($this->source[$pos] ?? '') !== '=') {
                $attributes[] = new Attribute($name, null, false, $attrStart);
                continue;
            }
            $pos++;

            $quote = $this->source[$pos] ?? '';
            if ($quote === '"' || $quote === "'") {
                $close = strpos($this->source, $quote, $pos + 1);
                if ($close === false) {
                    throw new TokenizerException('Unterminated quoted value', $pos);
                }
                $value = substr($this->source, $pos + 1, $close - $pos - 1);
                $pos = $close + 1;
                $quoted = true;
            } else {
                [$value, $pos] = $this->readUnquotedValue($pos, $tagName);
                $quoted = false;
            }

            if (str_contains($value, '<%')) {
                throw new TokenizerException(
                    "Template tags are not allowed inside component attributes on <:{$tagName}>; use \$Variables",
                    $tagStart
                );
            }
            $attributes[] = new Attribute($name, $value, $quoted, $attrStart);
        }
    }

    /**
     * Reads an unquoted value up to whitespace, ">" or "/>", allowing spaces and quotes
     * inside parentheses, e.g. $Page.Children(1, "a b").
     *
     * @return array{string, int} value, new position
     */
    private function readUnquotedValue(int $pos, string $tagName): array
    {
        $start = $pos;
        $depth = 0;
        $quote = null;
        $length = strlen($this->source);

        for (; $pos < $length; $pos++) {
            $char = $this->source[$pos];
            if ($quote !== null) {
                if ($char === $quote) {
                    $quote = null;
                }
                continue;
            }
            if ($depth > 0 && ($char === '"' || $char === "'")) {
                $quote = $char;
            } elseif ($char === '(') {
                $depth++;
            } elseif ($char === ')') {
                $depth--;
            } elseif ($depth === 0 && (ctype_space($char) || $char === '>' || substr($this->source, $pos, 2) === '/>')) {
                break;
            }
        }

        if ($pos === $start) {
            throw new TokenizerException("Invalid attribute syntax in <:{$tagName}>", $start);
        }
        return [substr($this->source, $start, $pos - $start), $pos];
    }
}
