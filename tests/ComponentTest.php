<?php

namespace WeDevelop\Components\Tests;

use LogicException;
use Psr\Log\AbstractLogger;
use Psr\Log\LoggerInterface;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Core\Kernel;
use SilverStripe\Dev\SapphireTest;
use SilverStripe\ORM\FieldType\DBHTMLText;
use WeDevelop\Components\ClassMerger;
use WeDevelop\Components\Component;
use WeDevelop\Components\Exception\InvalidComponentPropException;
use WeDevelop\Components\SimpleClassMerger;
use WeDevelop\Components\Tests\Fixtures\AlertComponent;

class ComponentTest extends SapphireTest
{
    protected $usesDatabase = false;

    public function testDefaultsAndDeclaredProps(): void
    {
        $alert = new AlertComponent('Alert', ['message' => 'Saved']);
        $this->assertSame('Saved', $alert->prop('message'));
        $this->assertSame('info', $alert->prop('tone'));
        $this->assertNull($alert->prop('icon'));
        $this->assertSame('Alert', $alert->getComponentName());
    }

    public function testPropsAreTemplateFieldsWithUppercaseName(): void
    {
        $alert = new AlertComponent('Alert', ['message' => 'Saved']);
        $this->assertTrue($alert->hasField('Message'));
        $this->assertSame('Saved', $alert->Message);
        $this->assertSame('SAVED', $alert->Shout);
    }

    public function testMissingRequiredPropThrowsOutsideLive(): void
    {
        $this->expectException(InvalidComponentPropException::class);
        $this->expectExceptionMessage('Missing required prop "message" on component <:Alert>');
        new AlertComponent('Alert', []);
    }

    public function testInvalidOptionThrowsOutsideLive(): void
    {
        $this->expectException(InvalidComponentPropException::class);
        $this->expectExceptionMessage('Invalid value "nope" for prop "tone" on component <:Alert>. Allowed: info, danger');
        new AlertComponent('Alert', ['message' => 'x', 'tone' => 'nope']);
    }

    public function testInvalidPropInLiveLogsAndFallsBackToDefault(): void
    {
        $logger = new class extends AbstractLogger {
            public array $records = [];
            public function log($level, \Stringable|string $message, array $context = []): void
            {
                $this->records[] = [$level, (string) $message];
            }
        };
        Injector::inst()->registerService($logger, LoggerInterface::class);
        $kernel = Injector::inst()->get(Kernel::class);
        $previous = $kernel->getEnvironment();
        $kernel->setEnvironment(Kernel::LIVE);
        try {
            $alert = new AlertComponent('Alert', ['message' => 'x', 'tone' => 'nope']);
        } finally {
            $kernel->setEnvironment($previous);
        }
        $this->assertSame('info', $alert->prop('tone'));
        $this->assertSame('warning', $logger->records[0][0]);
        $this->assertStringContainsString('Invalid value "nope"', $logger->records[0][1]);
    }

    public function testClassesMergeBaseVariantsInternalAndCallerClass(): void
    {
        $alert = new AlertComponent('Alert', ['message' => 'x', 'tone' => 'danger', 'class' => 'p-8']);
        $this->assertSame('rounded bg-red-50 text-red-900 p-8', $alert->Classes());
        $this->assertSame('rounded bg-red-50 text-red-900 shadow p-8', $alert->Classes('p-2 shadow'));
    }

    public function testClassMergerCanBeSwappedViaInjector(): void
    {
        Injector::inst()->registerService(new SimpleClassMerger(), ClassMerger::class);
        $alert = new AlertComponent('Alert', ['message' => 'x', 'tone' => 'danger', 'class' => 'p-8']);
        $this->assertSame('rounded p-4 bg-red-50 text-red-900 p-8', $alert->Classes());
    }

    public function testUndeclaredAttributesGoToAttributesOnly(): void
    {
        $alert = new AlertComponent('Alert', ['message' => 'x', 'id' => 'msg', 'aria-live' => 'polite', 'class' => 'p-8']);
        $this->assertFalse($alert->hasField('Id'));
        $this->assertSame(' id="msg" aria-live="polite"', $alert->Attributes()->getValue());
        $this->assertInstanceOf(DBHTMLText::class, $alert->Attributes());
    }

    public function testGenericComponentExposesAllAttributesAsFieldsAndAttributes(): void
    {
        $card = new Component('Card', ['title' => 'Hi', 'id' => 'c', 'class' => 'p-2']);
        $this->assertSame('Hi', $card->Title);
        $this->assertSame(' title="Hi" id="c"', $card->Attributes()->getValue());
        $this->assertSame('p-2', $card->Classes());
        $this->assertSame('rounded p-2', (new Component('Card', ['class' => 'p-2']))->Classes('rounded p-4'));
    }

    public function testSlots(): void
    {
        $calls = 0;
        $component = new Component('Card', [], [
            'default' => function () use (&$calls) {
                $calls++;
                return '<b>body</b>';
            },
            'footer' => fn () => '<i>foot</i>',
        ]);
        $this->assertTrue($component->HasChildren());
        $this->assertSame('<b>body</b>', $component->Children()->getValue());
        $component->Children();
        $this->assertSame(1, $calls, 'slot closures render once');
        $this->assertTrue($component->HasSlot('footer'));
        $this->assertSame('<i>foot</i>', $component->Slot('footer')->getValue());
        $this->assertFalse($component->HasSlot('aside'));
        $this->assertSame('', $component->Slot('aside')->getValue());
        $this->assertFalse((new Component('Card'))->HasChildren());
    }

    public function testClientPropsContainOnlyScalars(): void
    {
        $alert = new AlertComponent('Alert', ['message' => 'x', 'icon' => new \stdClass()]);
        $this->assertSame(['message' => 'x', 'tone' => 'info'], $alert->getClientProps());
    }

    public function testReservedPropNamesAreRejected(): void
    {
        AlertComponent::config()->merge('props', ['slot' => null]);
        $this->expectException(LogicException::class);
        $this->expectExceptionMessage('Prop name "slot" on ' . AlertComponent::class . ' is reserved');
        new AlertComponent('Alert', ['message' => 'x']);
    }
}
