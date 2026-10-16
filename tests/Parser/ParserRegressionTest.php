<?php

namespace WeDevelop\Components\Tests\Parser;

use PHPUnit\Framework\Attributes\DataProvider;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\TemplateEngine\SSTemplateParser;
use Throwable;
use WeDevelop\Components\Parser\ComponentParser;
use WeDevelop\Components\Tests\Fixtures\AlwaysTokenizingParser;

class ParserRegressionTest extends SapphireTest
{
    protected $usesDatabase = false;

    private function compile(SSTemplateParser $parser, string $source, string $name): string
    {
        try {
            return $parser->compileString($source, $name);
        } catch (Throwable $e) {
            return get_class($e) . ': ' . $e->getMessage();
        }
    }

    /**
     * @return array<string, array{class-string<SSTemplateParser>}>
     */
    public static function parserProvider(): array
    {
        return [
            'fast path' => [ComponentParser::class],
            'tokenizer path' => [AlwaysTokenizingParser::class],
        ];
    }

    /**
     * @param class-string<SSTemplateParser> $parserClass
     */
    #[DataProvider('parserProvider')]
    public function testCoreTemplatesCompileIdentically(string $parserClass): void
    {
        $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(BASE_PATH . '/vendor/silverstripe'));
        $count = 0;
        foreach ($files as $file) {
            if ($file->getExtension() !== 'ss') {
                continue;
            }
            $source = file_get_contents($file->getPathname());
            $this->assertSame(
                $this->compile(new SSTemplateParser(), $source, $file->getPathname()),
                $this->compile(new $parserClass(), $source, $file->getPathname()),
                $file->getPathname()
            );
            $count++;
        }
        // A standalone install has ~86 core templates (framework, template-engine, ...); a project
        // with the CMS has more. The floor only guards against the corpus silently vanishing.
        $this->assertGreaterThan(75, $count);
    }

    public function testNonComponentColonSyntaxCompilesIdentically(): void
    {
        $source = '<svg xmlns:xlink="x"><x:Foo/></svg> a <: b <:foo>c</:foo> $Title';
        $this->assertSame(
            (new SSTemplateParser())->compileString($source),
            (new ComponentParser())->compileString($source)
        );
    }
}
