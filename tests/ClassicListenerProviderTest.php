<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher\Tests;

use ArrayIterator;
use Closure;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionProperty;
use Solventt\EventDispatcher\Exceptions\NotFoundListenersException;
use Solventt\EventDispatcher\Providers\ClassicListenerProvider;
use Solventt\EventDispatcher\Tests\Mocks\Events\FirstEvent;
use Solventt\EventDispatcher\Tests\Mocks\Events\SecondEvent;
use Solventt\EventDispatcher\Tests\Mocks\Listeners\ArrayCallable;
use Solventt\EventDispatcher\Tests\Mocks\Listeners\InvokableClass;
use stdClass;

class ClassicListenerProviderTest extends TestCase
{
    private Closure $closure;
    private array $listenersDefinition;
    private ListenerProviderInterface $provider;

    protected function setUp(): void
    {
        $this->closure = function (FirstEvent $event): void {};

        $this->listenersDefinition = [
            FirstEvent::class => [
                new InvokableClass(),
                [ArrayCallable::class, 'test'],
            ],
            SecondEvent::class => [
                ['usualFunc', '1'],
                [[new ArrayCallable(), 'test2'], 2],
                $this->closure
            ]
        ];

        $this->provider = new ClassicListenerProvider($this->listenersDefinition);
    }

    public function testDifferentDefinitionsInConstructor()
    {
        $listeners = new ReflectionProperty($this->provider, 'listeners');
        $listeners->setAccessible(true);

        $expected = [
            FirstEvent::class => [
                [new InvokableClass(), 0],
                [[ArrayCallable::class, 'test'], 0],
            ],
            SecondEvent::class => [
                ['usualFunc', 0],
                [[new ArrayCallable(), 'test2'], 2],
                [$this->closure, 0]
            ]
        ];

        self::assertEquals($expected, $listeners->getValue($this->provider));
    }

    public function testGettingOfListeners()
    {
        $providerWithIterator = new ClassicListenerProvider(new ArrayIterator($this->listenersDefinition));

        $expectedListeners = [
            new InvokableClass(),
            [ArrayCallable::class, 'test']
        ];

        self::assertEquals($expectedListeners, $this->provider->getListenersForEvent(new FirstEvent()));
        self::assertEquals($expectedListeners, $providerWithIterator->getListenersForEvent(new FirstEvent()));

        $expectedListeners = [
            [new ArrayCallable(), 'test2'],
            'usualFunc',
            $this->closure
        ];

        self::assertEquals($expectedListeners, $this->provider->getListenersForEvent(new SecondEvent()));
        self::assertEquals($expectedListeners, $providerWithIterator->getListenersForEvent(new SecondEvent()));
    }

    public function testNotIterableListeners()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => new InvokableClass()
        ]);

        $this->expectErrorMessage('Wrong listeners definition format. Iterable is needed');

        $provider->getListenersForEvent(new FirstEvent());
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGettingOfListenersWithoutSignatureChecking()
    {
        $provider = new ClassicListenerProvider([

            FirstEvent::class => [
                [new ArrayCallable(), 'moreThanOneParameter'],
                [[new ArrayCallable(), 'wrongReturnType'], 3],
            ],

            SecondEvent::class => [
                [ArrayCallable::class, 'noParameters'],
                [ArrayCallable::class, 'undefinedParameterType'],
            ]
        ], false);

        $provider->getListenersForEvent(new FirstEvent());
    }

    public function wrongListenerTypes(): array
    {
        return [
            [InvokableClass::class],
            [['usualString']],
            [34],
            [true],
            [new stdClass()]
        ];
    }

    /**
     * @dataProvider wrongListenerTypes
     * @var mixed $listener
     */
    public function testWrongListenerTypeInConstructor($listener)
    {
        $this->expectErrorMessage('Wrong type of the listener');

        new ClassicListenerProvider([
            FirstEvent::class => [$listener]
        ]);
    }

    public function testNotFoundListeners()
    {
        $this->expectException(NotFoundListenersException::class);

        (new ClassicListenerProvider())->getListenersForEvent(new FirstEvent());
    }

    public function testSortingWithPriority()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => [
                [new InvokableClass(), 4],
                [[ArrayCallable::class, 'test'], 8],
                [[new ArrayCallable(), 'test2'], 1],
                ['usualFunc', 2],
                [$this->closure, 10]
            ]
        ]);

        $expected = [
            $this->closure,
            [ArrayCallable::class, 'test'],
            new InvokableClass(),
            'usualFunc',
            [new ArrayCallable(), 'test2']
        ];

        self::assertEquals($expected, $provider->getListenersForEvent(new FirstEvent()));
    }

    public function testOnMethod()
    {
        $provider = new ClassicListenerProvider();

        $provider->on(SecondEvent::class, new InvokableClass(), 0);

        self::assertCount(1, $result = $provider->getListenersForEvent(new SecondEvent()));
        self::assertInstanceOf(InvokableClass::class, $result[0]);

        $provider->on(SecondEvent::class, [new ArrayCallable(), 'test2'], 0);

        self::assertCount(2, $result = $provider->getListenersForEvent(new SecondEvent()));
        self::assertIsArray($result[1]);

        $provider->on(FirstEvent::class, [ArrayCallable::class, 'test'], 0);
        self::assertCount(1, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertIsArray($result[0]);
    }

    public function testOffMethod()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => [
                [new InvokableClass(), 0],
                [[ArrayCallable::class, 'test'], 0],
            ]
        ]);

        self::assertCount(2, $provider->getListenersForEvent(new FirstEvent()));

        $provider->off(FirstEvent::class, [ArrayCallable::class, 'test']);

        self::assertCount(1, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertInstanceOf(InvokableClass::class, $result[0]);

        $provider->off(FirstEvent::class, new InvokableClass());

        self::assertCount(0, $provider->getListenersForEvent(new FirstEvent()));
    }
}