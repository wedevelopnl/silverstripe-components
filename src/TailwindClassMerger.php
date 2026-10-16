<?php

namespace WeDevelop\Components;

use LogicException;
use Psr\SimpleCache\CacheInterface;
use TailwindMerge\TailwindMerge;

/**
 * Merges Tailwind class strings, resolving conflicts so later classes win.
 * Requires gehrisandro/tailwind-merge-php.
 */
class TailwindClassMerger implements ClassMerger
{
    private ?TailwindMerge $merger = null;

    public function __construct(private ?CacheInterface $cache = null)
    {
        if (!class_exists(TailwindMerge::class)) {
            throw new LogicException(
                'TailwindClassMerger requires gehrisandro/tailwind-merge-php. Install it with '
                . '"composer require gehrisandro/tailwind-merge-php", or use SimpleClassMerger by setting '
                . 'SilverStripe\Core\Injector\Injector.WeDevelop\Components\ClassMerger.class to '
                . 'WeDevelop\Components\SimpleClassMerger in your YAML config.'
            );
        }
    }

    public function merge(?string ...$classes): string
    {
        $classes = array_values(array_filter(
            array_map(fn (?string $class) => trim((string) $class), $classes),
            fn (string $class) => $class !== ''
        ));

        return $classes ? $this->merger()->merge(...$classes) : '';
    }

    private function merger(): TailwindMerge
    {
        if (!$this->merger) {
            $factory = TailwindMerge::factory();
            if ($this->cache) {
                $factory = $factory->withCache($this->cache);
            }
            $this->merger = $factory->make();
        }
        return $this->merger;
    }
}
