<?php

namespace WeDevelop\Components\Parser;

/**
 * @internal A tag found by the Tokenizer.
 */
final class Tag
{
    public const OPEN = 'open';
    public const CLOSE = 'close';
    public const SLOT_OPEN = 'slot-open';
    public const SLOT_CLOSE = 'slot-close';

    /**
     * @param list<Attribute> $attributes
     */
    public function __construct(
        public readonly string $kind,
        public readonly string $name,
        public readonly array $attributes,
        public readonly bool $selfClosing,
        public readonly int $start,
        public readonly int $end,
    ) {
    }
}
