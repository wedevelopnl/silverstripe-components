<?php

namespace WeDevelop\Components\Parser;

use Closure;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\TemplateEngine\Exception\SSTemplateParseException;
use SilverStripe\TemplateEngine\SSTemplateParser;
use WeDevelop\Components\ComponentRenderer;

/**
 * Compiles one component call into PHP that appends the rendered component to $val.
 *
 * Slots become closures capturing the caller's $scope, so slot content renders in the
 * caller's scope when the component asks for it.
 */
final class CallCompiler
{
    /**
     * @param string $source The template source the node's offsets refer to, for line numbers in errors
     */
    public function __construct(private string $templateName = '', private string $source = '')
    {
    }

    public function compile(ComponentNode $node): string
    {
        $props = [];
        foreach ($node->attributes as $attribute) {
            $props[] = var_export($attribute->name, true) . ' => ' . $this->compileValue($attribute);
        }

        $slots = [];
        // Ignore the line-keeping comments of removed slots when deciding if there is default content
        if (trim(preg_replace('/<%--\s*--%>/', '', $node->defaultBody)) !== '') {
            $slots[] = "'default' => " . $this->compileSlot($node->defaultBody, $node->defaultBodyOffset);
        }
        foreach ($node->slots as $name => $body) {
            $slots[] = var_export($name, true) . ' => ' . $this->compileSlot($body, $node->slotOffsets[$name] ?? 0);
        }

        return '$val .= \\' . ComponentRenderer::class . '::render('
            . var_export($node->name, true) . ', '
            . '[' . implode(', ', $props) . '], '
            . '[' . implode(', ', $slots) . '], '
            . '$scope);' . PHP_EOL;
    }

    private function compileValue(Attribute $attribute): string
    {
        if ($attribute->value === null) {
            return 'true';
        }
        if (!$attribute->quoted) {
            return $this->atLineOf($attribute->offset, fn () => $this->parser()->compileArgumentValue($attribute->value));
        }
        if (!str_contains($attribute->value, '$')) {
            return var_export(html_entity_decode($attribute->value, ENT_QUOTES | ENT_HTML5, 'UTF-8'), true);
        }
        // Interpolate like template text, then decode so the prop holds plain text that is
        // escaped exactly once when the component outputs it.
        $php = $this->atLineOf(
            $attribute->offset,
            fn () => $this->parser()->compileFragment($attribute->value, $this->templateName)
        );
        return '\html_entity_decode((function () use ($scope) { $val = \'\'; ' . $php
            . ' return $val; })(), ENT_QUOTES | ENT_HTML5, \'UTF-8\')';
    }

    private function compileSlot(string $body, int $offset): string
    {
        $php = $this->atLineOf($offset, fn () => $this->parser()->compileFragment($body, $this->templateName));
        return 'function (array $slotProps = []) use ($scope, $cache) { $val = \'\';' . PHP_EOL
            . $php . PHP_EOL
            . 'return $val; }';
    }

    /**
     * Runs a nested compilation of source starting at $offset, making the line of any parse
     * error absolute in the enclosing template.
     */
    private function atLineOf(int $offset, Closure $compile): string
    {
        try {
            return $compile();
        } catch (SSTemplateParseException $e) {
            throw ComponentParseException::fromFragment(
                $e,
                ComponentParseException::lineAt($this->source, $offset),
                $this->templateName
            );
        }
    }

    /**
     * A fresh parser via the Injector, so project-configured blocks are available in slots and props.
     */
    private function parser(): ComponentParser
    {
        return Injector::inst()->create(SSTemplateParser::class);
    }
}
