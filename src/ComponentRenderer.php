<?php

namespace WeDevelop\Components;

use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\TemplateEngine\ScopeManager;
use SilverStripe\TemplateEngine\SSTemplateEngine;
use SilverStripe\View\SSViewer;
use SilverStripe\View\ViewLayerData;
use WeDevelop\Components\Exception\ComponentNotFoundException;

/**
 * Renders a component call. Compiled templates call render() directly.
 */
class ComponentRenderer
{
    use Injectable;

    /**
     * @param array<string, mixed> $attributes
     * @param array<string, \Closure> $slots
     * @param ScopeManager|null $callerScope Caller scope; unused in v1, reserved for scoped slots
     */
    public static function render(string $name, array $attributes, array $slots, ?ScopeManager $callerScope = null): string
    {
        return static::singleton()->renderComponent($name, $attributes, $slots);
    }

    public function renderComponent(string $name, array $attributes = [], array $slots = []): string
    {
        $class = ComponentRegistry::singleton()->classFor($name) ?? Component::class;
        /** @var Component $component */
        $component = Injector::inst()->createWithArgs($class, [$name, $attributes, $slots]);

        $templates = $this->templateCandidates($component, $name);
        $engine = SSTemplateEngine::create();
        if (!$engine->hasTemplate($templates)) {
            throw new ComponentNotFoundException(sprintf(
                'Component <:%s> not found. Looked for template(s): %s (themes: %s)',
                $name,
                implode(', ', $templates),
                implode(', ', SSViewer::get_themes())
            ));
        }
        $engine->setTemplate($templates);

        // Whitespace around a call belongs to the caller's template, not to the component
        $html = trim($engine->render(ViewLayerData::create($component)));
        $component->extend('updateRenderedHTML', $html);
        return $html;
    }

    /**
     * @return list<string>
     */
    private function templateCandidates(Component $component, string $name): array
    {
        $default = 'Components/' . str_replace('.', '/', $name);
        $custom = (string) $component->config()->get('template');
        return $custom ? [$custom, $default] : [$default];
    }
}
