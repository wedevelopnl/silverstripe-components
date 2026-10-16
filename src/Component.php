<?php

namespace WeDevelop\Components;

use Closure;
use LogicException;
use Psr\Log\LoggerInterface;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Model\ModelData;
use SilverStripe\ORM\FieldType\DBHTMLText;
use WeDevelop\Components\Exception\InvalidComponentPropException;

/**
 * Model for a rendered <:Component>. Subclass it to declare props, variants and computed props;
 * components without a class are rendered with a plain Component instance.
 */
class Component extends ModelData
{
    /**
     * Prop definitions: name => null (optional, default null) or
     * ['default' => mixed, 'required' => bool, 'options' => list<scalar>].
     */
    private static array $props = [];

    /**
     * Classes always applied by $Classes.
     */
    private static string $base = '';

    /**
     * Prop name => [prop value => complete class string].
     */
    private static array $variants = [];

    /**
     * Name used in templates (<:Name>); derived from the class name when empty.
     */
    private static string $component_name = '';

    /**
     * Template to render instead of Components/<Name>.
     */
    private static string $template = '';

    private const RESERVED = ['children', 'hasChildren', 'slot', 'hasSlot', 'classes', 'attributes', 'class'];

    private string $name;

    /** @var array<string, mixed> */
    private array $propValues = [];

    /** @var array<string, mixed> */
    private array $extraAttributes = [];

    private ?string $callerClass = null;

    /** @var array<string, Closure> */
    private array $slots;

    /** @var array<string, string> */
    private array $renderedSlots = [];

    /**
     * @param array<string, mixed> $attributes Attributes from the call, including "class"
     * @param array<string, Closure> $slots Slot closures; "default" holds the children
     */
    public function __construct(string $name, array $attributes = [], array $slots = [])
    {
        parent::__construct();
        $this->name = $name;
        $this->slots = $slots;

        if (array_key_exists('class', $attributes)) {
            $this->callerClass = $this->stringValue($attributes['class']);
            unset($attributes['class']);
        }

        if (static::class === self::class) {
            $this->propValues = array_diff_key($attributes, array_flip(self::RESERVED));
            $this->extraAttributes = $attributes;
            return;
        }

        $props = [];
        foreach ($this->propDefinitions() as $prop => $definition) {
            if (array_key_exists($prop, $attributes)) {
                $props[$prop] = $this->validate($prop, $attributes[$prop], $definition);
                unset($attributes[$prop]);
            } elseif ($definition['required']) {
                $props[$prop] = $this->violation(
                    sprintf('Missing required prop "%s" on component <:%s>', $prop, $this->name),
                    $definition['default']
                );
            } else {
                $props[$prop] = $definition['default'];
            }
        }
        $this->propValues = $this->resolveProps($props);
        $this->extraAttributes = $attributes;
    }

    /**
     * Hook for subclasses to derive prop values after defaults and validation.
     *
     * @param array<string, mixed> $props
     * @return array<string, mixed>
     */
    protected function resolveProps(array $props): array
    {
        return $props;
    }

    public function getComponentName(): string
    {
        return $this->name;
    }

    public function prop(string $name): mixed
    {
        return $this->propValues[$name] ?? null;
    }

    /**
     * Scalar props, for serialising to client-side code later on.
     *
     * @return array<string, scalar>
     */
    public function getClientProps(): array
    {
        return array_filter($this->propValues, fn ($value) => is_scalar($value));
    }

    public function hasField(string $fieldName): bool
    {
        return $this->propKey($fieldName) !== null || parent::hasField($fieldName);
    }

    public function getField(string $fieldName): mixed
    {
        $key = $this->propKey($fieldName);
        return $key !== null ? $this->propValues[$key] : parent::getField($fieldName);
    }

    public function Children(): DBHTMLText
    {
        return $this->Slot('default');
    }

    public function HasChildren(): bool
    {
        return $this->HasSlot('default');
    }

    public function Slot(string $name): DBHTMLText
    {
        if (!array_key_exists($name, $this->renderedSlots)) {
            $this->renderedSlots[$name] = isset($this->slots[$name]) ? (string) ($this->slots[$name])() : '';
        }
        return DBHTMLText::create()->setValue($this->renderedSlots[$name]);
    }

    /**
     * True when the slot was passed and renders more than whitespace, so a slot whose content
     * is wrapped in a false <% if %> counts as absent.
     */
    public function HasSlot(string $name): bool
    {
        return isset($this->slots[$name]) && trim((string) $this->Slot($name)->getValue()) !== '';
    }

    /**
     * Merged classes: $base, selected variants, $internal, then the caller's class attribute.
     */
    public function Classes(string $internal = ''): string
    {
        $variantClasses = [];
        foreach ((array) static::config()->get('variants') as $prop => $map) {
            $value = $this->stringValue($this->propValues[$prop] ?? null);
            if ($value !== null && isset($map[$value])) {
                $variantClasses[] = $map[$value];
            }
        }

        return Injector::inst()->get(ClassMerger::class)->merge(
            (string) static::config()->get('base'),
            ...[...$variantClasses, $internal, $this->callerClass]
        );
    }

    public function Attributes(): DBHTMLText
    {
        return DBHTMLText::create()->setValue(HtmlAttributes::render($this->extraAttributes));
    }

    /**
     * @return array<string, array{default: mixed, required: bool, options: ?list<scalar>}>
     */
    private function propDefinitions(): array
    {
        $definitions = [];
        foreach ((array) static::config()->get('props') as $prop => $definition) {
            if (in_array($prop, self::RESERVED, true)) {
                throw new LogicException(sprintf('Prop name "%s" on %s is reserved', $prop, static::class));
            }
            $definition = is_array($definition) ? $definition : [];
            $definitions[$prop] = [
                'default' => $definition['default'] ?? null,
                'required' => (bool) ($definition['required'] ?? false),
                'options' => isset($definition['options']) ? array_values($definition['options']) : null,
            ];
        }
        return $definitions;
    }

    private function validate(string $prop, mixed $value, array $definition): mixed
    {
        if ($definition['options'] === null) {
            return $value;
        }
        $options = array_map('strval', $definition['options']);
        if (in_array($this->stringValue($value), $options, true)) {
            return $value;
        }
        return $this->violation(
            sprintf(
                'Invalid value "%s" for prop "%s" on component <:%s>. Allowed: %s',
                $this->stringValue($value),
                $prop,
                $this->name,
                implode(', ', $options)
            ),
            $definition['default']
        );
    }

    private function violation(string $message, mixed $fallback): mixed
    {
        if (!Director::isLive()) {
            throw new InvalidComponentPropException($message);
        }
        Injector::inst()->get(LoggerInterface::class)->warning($message);
        return $fallback;
    }

    private function propKey(string $fieldName): ?string
    {
        foreach ([$fieldName, lcfirst($fieldName)] as $key) {
            if (array_key_exists($key, $this->propValues)) {
                return $key;
            }
        }
        return null;
    }

    /**
     * Raw string form of a prop value, for class merging and option comparison.
     */
    private function stringValue(mixed $value): ?string
    {
        $value = TemplateValue::scalar($value);
        if (is_bool($value)) {
            return $value ? '1' : '0';
        }
        return $value === null ? null : (string) $value;
    }
}
