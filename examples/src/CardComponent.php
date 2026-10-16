<?php

namespace App\Components;

use WeDevelop\Components\Component;

/**
 * Declares title as a prop so it renders only in the heading, not as an HTML attribute
 * on the card's root element.
 */
class CardComponent extends Component
{
    private static array $props = [
        'title' => null,
    ];
}
