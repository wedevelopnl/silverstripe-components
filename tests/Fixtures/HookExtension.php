<?php

namespace WeDevelop\Components\Tests\Fixtures;

use SilverStripe\Core\Extension;
use SilverStripe\Dev\TestOnly;

class HookExtension extends Extension implements TestOnly
{
    public static bool $enabled = false;

    protected function updateRenderedHTML(string &$html): void
    {
        if (static::$enabled) {
            $html = '<div data-component="' . $this->getOwner()->getComponentName() . '">' . trim($html) . '</div>';
        }
    }
}
