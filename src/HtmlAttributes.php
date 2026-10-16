<?php

namespace WeDevelop\Components;

/**
 * Renders an attribute array as an HTML attribute string.
 *
 * Template values (DBField, ViewLayerData) render their raw value, escaped once.
 * true renders a bare attribute, false/null are omitted, values without a scalar value are omitted.
 */
final class HtmlAttributes
{
    private const VALID_NAME = '/^[A-Za-z_@:][A-Za-z0-9_\-:.@]*$/';

    public static function render(array $attributes): string
    {
        $html = '';
        foreach ($attributes as $name => $value) {
            $value = TemplateValue::scalar($value);
            if (!preg_match(self::VALID_NAME, (string) $name) || $value === null || $value === false) {
                continue;
            }
            if ($value === true) {
                $html .= ' ' . $name;
                continue;
            }
            $html .= sprintf(' %s="%s"', $name, htmlspecialchars((string) $value, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        }
        return $html;
    }
}
