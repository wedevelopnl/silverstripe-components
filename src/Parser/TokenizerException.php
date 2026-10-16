<?php

namespace WeDevelop\Components\Parser;

use RuntimeException;

class TokenizerException extends RuntimeException
{
    public function __construct(string $message, public readonly int $offset)
    {
        parent::__construct($message);
    }
}
