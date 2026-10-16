<?php

namespace WeDevelop\Components\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use WeDevelop\Components\Parser\ComponentParser;

/**
 * ComponentParser without the "no <:" fast path, so every template goes through the Tokenizer.
 */
class AlwaysTokenizingParser extends ComponentParser implements TestOnly
{
    #[\Override]
    protected function mayContainComponents(string $source): bool
    {
        return true;
    }
}
