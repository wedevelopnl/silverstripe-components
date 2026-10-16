<?php

namespace WeDevelop\Components\Tests;

use SilverStripe\Dev\SapphireTest;
use WeDevelop\Components\Component;
use WeDevelop\Components\ComponentRenderer;
use WeDevelop\Components\Exception\ComponentNotFoundException;
use WeDevelop\Components\Tests\Fixtures\HookExtension;

class ComponentRendererTest extends SapphireTest
{
    protected $usesDatabase = false;

    protected function setUp(): void
    {
        parent::setUp();
        // Not via $required_extensions: that forces a temp-database rebuild, which this DB-less test must avoid.
        Component::add_extension(HookExtension::class);
    }

    protected function tearDown(): void
    {
        HookExtension::$enabled = false;
        Component::remove_extension(HookExtension::class);
        parent::tearDown();
    }

    private function render(string $name, array $attributes = [], array $slots = []): string
    {
        return trim(ComponentRenderer::render($name, $attributes, $slots));
    }

    public function testRendersTemplateOnlyComponent(): void
    {
        $this->assertSame(
            '<div class="p-2" title="Hi">[Hi][<b>x</b>][]</div>',
            $this->render('Plain', ['title' => 'Hi'], ['default' => fn () => '<b>x</b>'])
        );
    }

    public function testRendersDottedName(): void
    {
        $this->assertSame('<span class="badge">New</span>', $this->render('Ui.Badge', ['label' => 'New']));
    }

    public function testOutputIsTrimmed(): void
    {
        $this->assertSame('<span class="badge">x</span>', ComponentRenderer::render('Ui.Badge', ['label' => 'x'], []));
    }

    public function testRendersClassComponent(): void
    {
        $this->assertSame(
            '<div class="rounded bg-red-50 text-red-900 p-8" id="m" role="alert">Saved</div>',
            $this->render('Alert', ['message' => 'Saved', 'tone' => 'danger', 'class' => 'p-8', 'id' => 'm'])
        );
    }

    public function testEscapesStringProps(): void
    {
        $this->assertStringContainsString('[Tom &amp; Jerry]', $this->render('Plain', ['title' => 'Tom & Jerry']));
    }

    public function testUnknownComponentThrows(): void
    {
        $this->expectException(ComponentNotFoundException::class);
        $this->expectExceptionMessage('Component <:Nope> not found. Looked for template(s): Components/Nope');
        $this->render('Nope');
    }

    public function testExtensionHookCanWrapOutput(): void
    {
        HookExtension::$enabled = true;
        $this->assertSame(
            '<div data-component="Ui.Badge"><span class="badge">x</span></div>',
            $this->render('Ui.Badge', ['label' => 'x'])
        );
    }
}
