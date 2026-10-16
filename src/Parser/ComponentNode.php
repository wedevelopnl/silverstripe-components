<?php

namespace WeDevelop\Components\Parser;

/**
 * One <:Component> call found in a template source.
 */
final class ComponentNode
{
    /**
     * @param list<Attribute> $attributes
     * @param string $defaultBody Body source outside of <:slot> tags; a removed slot that spans
     *                            lines leaves a template comment holding its newlines, so lines in
     *                            the default body match the template
     * @param array<string, string> $slots Named slot bodies, keyed by slot name
     * @param int $offset Start of the call in the source
     * @param int $length Length of the whole call, including closing tag
     * @param int $defaultBodyOffset Start of the default body in the source
     * @param array<string, int> $slotOffsets Start of each named slot body in the source
     */
    public function __construct(
        public readonly string $name,
        public readonly array $attributes,
        public readonly string $defaultBody,
        public readonly array $slots,
        public readonly int $offset,
        public readonly int $length,
        public readonly int $defaultBodyOffset = 0,
        public readonly array $slotOffsets = [],
    ) {
    }
}
