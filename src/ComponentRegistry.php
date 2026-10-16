<?php

namespace WeDevelop\Components;

use ReflectionClass;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injectable;
use WeDevelop\Components\Exception\DuplicateComponentException;

/**
 * Maps component names (<:Name>) to Component subclasses.
 */
class ComponentRegistry
{
    use Injectable;

    /** @var array<string, class-string<Component>>|null */
    private ?array $map = null;

    public function classFor(string $name): ?string
    {
        return $this->map()[$name] ?? null;
    }

    public static function nameForClass(string $class): string
    {
        $configured = Config::inst()->get($class, 'component_name', Config::UNINHERITED);
        if ($configured) {
            return $configured;
        }
        $short = ClassInfo::shortName($class);
        return str_ends_with($short, 'Component') && $short !== 'Component'
            ? substr($short, 0, -strlen('Component'))
            : $short;
    }

    /**
     * @param array<string, string> $classToName
     * @return array<string, string> name => class
     */
    public static function buildMap(array $classToName): array
    {
        $map = [];
        foreach ($classToName as $class => $name) {
            $existing = $map[$name] ?? null;
            if ($existing === null || is_subclass_of($class, $existing)) {
                $map[$name] = $class;
                continue;
            }
            if (is_subclass_of($existing, $class)) {
                continue;
            }
            throw new DuplicateComponentException(sprintf(
                'Component name "%s" is used by both %s and %s. Set $component_name on one of them.',
                $name,
                $existing,
                $class
            ));
        }
        return $map;
    }

    private function map(): array
    {
        if ($this->map === null) {
            $classToName = [];
            foreach (ClassInfo::subclassesFor(Component::class, false) as $class) {
                if (!(new ReflectionClass($class))->isAbstract()) {
                    $classToName[$class] = static::nameForClass($class);
                }
            }
            $this->map = static::buildMap($classToName);
        }
        return $this->map;
    }
}
