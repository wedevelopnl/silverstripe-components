<?php

namespace App\Components;

use WeDevelop\Components\Component;

class ButtonComponent extends Component
{
    private static array $props = [
        'label' => null,
        'href' => null,
        'type' => ['default' => 'button', 'options' => ['button', 'submit', 'reset']],
        'variant' => ['default' => 'primary', 'options' => ['primary', 'secondary', 'ghost']],
        'size' => ['default' => 'md', 'options' => ['sm', 'md', 'lg']],
    ];

    private static string $base = 'inline-flex items-center justify-center gap-2 rounded-lg font-medium transition-colors focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 disabled:pointer-events-none disabled:opacity-50';

    private static array $variants = [
        'variant' => [
            'primary' => 'bg-brand-600 text-white hover:bg-brand-700',
            'secondary' => 'bg-white text-gray-900 ring-1 ring-inset ring-gray-300 hover:bg-gray-50',
            'ghost' => 'text-brand-700 hover:bg-brand-50',
        ],
        'size' => [
            'sm' => 'px-3 py-1.5 text-sm',
            'md' => 'px-4 py-2 text-base',
            'lg' => 'px-6 py-3 text-lg',
        ],
    ];

    public function getTag(): string
    {
        return $this->prop('href') ? 'a' : 'button';
    }
}
