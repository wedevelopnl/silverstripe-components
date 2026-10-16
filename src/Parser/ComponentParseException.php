<?php

namespace WeDevelop\Components\Parser;

use Exception;
use SilverStripe\TemplateEngine\Exception\SSTemplateParseException;
use Throwable;

/**
 * Parse error raised for (code inside) a component call, with the absolute line in the
 * template and the template name when known.
 */
class ComponentParseException extends SSTemplateParseException
{
    private const NATIVE_MESSAGE = '/^Parse error in template on line (\d+)\. Error was: (.*)$/s';

    public function __construct(
        public readonly string $error,
        public readonly int $templateLine,
        public readonly string $templateName = '',
        ?Throwable $previous = null,
    ) {
        // The parent constructor derives the line from a parser's position; we already know it
        Exception::__construct(
            sprintf(
                'Parse error in template%s on line %d. Error was: %s',
                $templateName !== '' ? ' ' . $templateName : '',
                $templateLine,
                $error
            ),
            0,
            $previous
        );
    }

    /**
     * Rethrows an error from a fragment (slot body, attribute value) that starts on line
     * $startLine of the enclosing template, with the line made absolute.
     */
    public static function fromFragment(SSTemplateParseException $e, int $startLine, string $templateName): self
    {
        if ($e instanceof self) {
            return new self($e->error, $startLine + $e->templateLine - 1, $templateName ?: $e->templateName, $e);
        }
        if (preg_match(self::NATIVE_MESSAGE, $e->getMessage(), $match)) {
            return new self($match[2], $startLine + (int) $match[1] - 1, $templateName, $e);
        }
        return new self($e->getMessage(), $startLine, $templateName, $e);
    }

    /**
     * Line number of an offset in a source, counted like the native parser does.
     */
    public static function lineAt(string $source, int $offset): int
    {
        return preg_match_all('/\r\n|\r|\n/', substr($source, 0, $offset)) + 1;
    }
}
