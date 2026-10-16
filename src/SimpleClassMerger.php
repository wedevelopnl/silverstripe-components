<?php

namespace WeDevelop\Components;

/**
 * Joins class strings and removes duplicates, without resolving conflicts.
 * For projects without Tailwind (plain CSS, BEM, Bootstrap, Bulma, ...).
 */
class SimpleClassMerger implements ClassMerger
{
    public function merge(?string ...$classes): string
    {
        $tokens = preg_split('/\s+/', implode(' ', array_map('strval', $classes)), -1, PREG_SPLIT_NO_EMPTY);
        return implode(' ', array_unique($tokens));
    }
}
