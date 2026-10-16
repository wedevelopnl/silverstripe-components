<?php

namespace WeDevelop\Components\Parser;

use SilverStripe\TemplateEngine\Exception\SSTemplateParseException;
use SilverStripe\TemplateEngine\SSTemplateParser;

/**
 * Template parser with support for <:Component> calls.
 *
 * Component calls are replaced by <% component "__cN" %> open-block placeholders before the
 * native parser runs; the placeholder handler emits the compiled call. Templates without
 * "<:" are passed straight to the native parser.
 */
class ComponentParser extends SSTemplateParser
{
    /** @var array<string, ComponentNode> */
    private array $componentNodes = [];

    private string $componentTemplateName = '';

    private string $componentSource = '';

    public function compileString(
        string $string,
        string $templateName = "",
        bool $includeDebuggingComments = false,
        bool $topTemplate = true
    ): string {
        if (!$this->mayContainComponents($string)) {
            return parent::compileString($string, $templateName, $includeDebuggingComments, $topTemplate);
        }

        try {
            $nodes = (new Tokenizer())->tokenize($string);
        } catch (TokenizerException $e) {
            throw new ComponentParseException(
                $e->getMessage(),
                ComponentParseException::lineAt($string, $e->offset),
                $templateName,
                $e
            );
        }

        $this->componentNodes = [];
        $this->componentTemplateName = $templateName;
        $this->componentSource = $string;
        $source = '';
        $cursor = 0;
        foreach ($nodes as $index => $node) {
            $id = '__c' . $index;
            $this->componentNodes[$id] = $node;
            $newlines = substr_count(substr($string, $node->offset, $node->length), "\n");
            // Keep the newlines of the call (in a template comment, as the native grammar does not
            // allow them inside <% %>) so line numbers after the call stay correct
            $source .= substr($string, $cursor, $node->offset - $cursor)
                . '<% component "' . $id . '" %>'
                . ($newlines > 0 ? '<%--' . str_repeat("\n", $newlines) . '--%>' : '');
            $cursor = $node->offset + $node->length;
        }
        $source .= substr($string, $cursor);

        return parent::compileString($source, $templateName, $includeDebuggingComments, $topTemplate);
    }

    /**
     * Fast path: sources without "<:" cannot contain component calls.
     */
    protected function mayContainComponents(string $source): bool
    {
        return str_contains($source, '<:');
    }

    /**
     * Open-block handler for the <% component "__cN" %> placeholder.
     */
    function OpenBlock_Handle_Component(&$res)
    {
        $id = $res['ArgumentCount'] === 1 ? trim($res['Arguments'][0]['text'] ?? $res['Arguments'][0]['php'] ?? '', '\'"') : '';
        if (!isset($this->componentNodes[$id])) {
            throw new SSTemplateParseException(
                '<% component %> is reserved by wedevelopnl/silverstripe-components; use <:Name> tags instead',
                $this
            );
        }
        return (new CallCompiler($this->componentTemplateName, $this->componentSource))->compile($this->componentNodes[$id]);
    }

    /**
     * Compiles an unquoted attribute value (e.g. $Page, 3, true) to a PHP expression,
     * using the same rules as <% include %> arguments.
     */
    public function compileArgumentValue(string $value): string
    {
        $source = 'v=' . $value;
        \Parser::__construct($source);
        $result = $this->match_NamedArgument();
        if ($result === false || $this->pos !== strlen($source)) {
            throw new SSTemplateParseException(sprintf('Invalid component attribute value "%s"', $value), $this);
        }
        return substr($result['php'], strlen("'v' => "));
    }

    /**
     * Compiles a template fragment (slot body or interpolated value) to PHP statements appending to $val.
     */
    public function compileFragment(string $source, string $templateName = ''): string
    {
        // Compiled as a top template: the native non-top mode cannot handle blocks (the
        // $TemplateMatcher used by block rules is only set by TopTemplate). Strip its "<?php" stanza.
        $php = $this->compileString($source, $templateName, false, true);
        $stanza = '<?php' . PHP_EOL;
        return str_starts_with($php, $stanza) ? substr($php, strlen($stanza)) : $php;
    }
}
