<?php

namespace WeDevelop\Components;

/**
 * Merges class strings for $Classes: base, variants, internal classes and the caller's class.
 *
 * The implementation is chosen via the Injector; TailwindClassMerger is the default,
 * SimpleClassMerger suits projects without Tailwind.
 */
interface ClassMerger
{
    /**
     * Later classes take precedence; null and empty strings are ignored.
     */
    public function merge(?string ...$classes): string;
}
