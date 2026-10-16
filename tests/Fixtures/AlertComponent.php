<?php

namespace WeDevelop\Components\Tests\Fixtures;

use SilverStripe\Dev\TestOnly;
use WeDevelop\Components\Component;

class AlertComponent extends Component implements TestOnly
{
    private static array $props = [
        'message' => ['required' => true],
        'tone' => ['default' => 'info', 'options' => ['info', 'danger']],
        'icon' => null,
        'shape' => ['options' => ['round', 'a&b']],
    ];

    private static string $base = 'rounded p-4';

    private static array $variants = [
        'tone' => [
            'info' => 'bg-blue-50 text-blue-900',
            'danger' => 'bg-red-50 text-red-900',
        ],
        'shape' => [
            'a&b' => 'shadow',
        ],
    ];

    public function getShout(): string
    {
        return strtoupper((string) $this->prop('message'));
    }
}
