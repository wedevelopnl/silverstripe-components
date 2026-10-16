<?php

namespace App\Components;

use WeDevelop\Components\Component;

class HeadingComponent extends Component
{
    private static array $props = [
        'text' => null,
        'level' => ['default' => '2', 'options' => ['1', '2', '3', '4', '5', '6']],
        'size' => ['options' => ['sm', 'md', 'lg', 'xl', '2xl']],
    ];

    private static string $base = 'font-semibold tracking-tight text-balance text-gray-900';

    private static array $variants = [
        'size' => [
            'sm' => 'text-lg',
            'md' => 'text-xl',
            'lg' => 'text-2xl',
            'xl' => 'text-3xl sm:text-4xl',
            '2xl' => 'text-4xl sm:text-5xl',
        ],
    ];

    private const SIZE_FOR_LEVEL = ['1' => '2xl', '2' => 'xl', '3' => 'lg', '4' => 'md', '5' => 'sm', '6' => 'sm'];

    protected function resolveProps(array $props): array
    {
        $props['size'] ??= self::SIZE_FOR_LEVEL[(string) $props['level']];
        return $props;
    }
}
