<?php

namespace WeDevelop\Components;

use SilverStripe\ORM\FieldType\DBField;
use SilverStripe\View\ViewLayerData;

/**
 * @internal Unwraps values coming from templates to their raw (unescaped) scalar value.
 *
 * Unquoted attribute values such as data-id=$ID arrive as ViewLayerData wrapping a DBField;
 * their string form is already HTML-escaped, so it must not be used for attributes or classes.
 */
final class TemplateValue
{
    /**
     * The raw scalar for scalars, DBFields and ViewLayerData wrapping either; null for anything
     * else (lists, records, other objects).
     */
    public static function scalar(mixed $value): string|int|float|bool|null
    {
        if ($value instanceof ViewLayerData) {
            // "Me" returns the wrapped data; there is no other public accessor
            $value = $value->getRawDataValue('Me');
        }
        if ($value instanceof DBField) {
            $value = $value->getValue();
        }
        return is_scalar($value) ? $value : null;
    }
}
