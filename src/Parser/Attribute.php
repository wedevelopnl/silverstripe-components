<?php

namespace WeDevelop\Components\Parser;

/**
 * A single attribute on a <:Component> tag, as written in the template.
 */
final class Attribute
{
    /**
     * @param string|null $value Raw value without quotes; null for a bare boolean attribute
     */
    public function __construct(
        public readonly string $name,
        public readonly ?string $value,
        public readonly bool $quoted,
        public readonly int $offset,
    ) {
    }
}
