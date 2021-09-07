<?php

namespace Solventt\EventDispatcher\Tests;

use ArrayIterator;
use Closure;
use PHPUnit\Framework\TestCase;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionProperty;
use Solventt\EventDispatcher\Exceptions\NotFoundListenersException;
use Solventt\EventDispatcher\Providers\ReflectionListenerProvider;
use Solventt\EventDispatcher\Tests\Mocks\Events\FirstEvent;
use Solventt\EventDispatcher\Tests\Mocks\Events\SecondEvent;
use Solventt\EventDispatcher\Tests\Mocks\Listeners\ArrayCallable;
use Solventt\EventDispatcher\Tests\Mocks\Listeners\InvokableClass;
use stdClass;

class ReflectionListenerProviderTest extends TestCase
{
    private Closure $closure;
    private array $listenersDefinition;
    private ListenerProviderInterface $provider;

    protected function setUp(): void
    {
        $this->closure = function (stdClass $event): void {};

        $this->listenersDefinition = [
            new InvokableClass(),
            [ArrayCallable::class, 'test'],
            [[new ArrayCallable(), 'test2'], 2],
            [ArrayCallable::class, 'test3'],
            'usualFunc',
            [$this->closure, '5']
        ];

        $this->provider = new ReflectionListenerProvider($this->listenersDefinition);
    }

    public function testNormalConstructorWork()
    {
        $expected = [
            FirstEvent::class => [
                [new InvokableClass(), 0],
                [[ArrayCallable::class, 'test'], 0],
                [[new ArrayCallable(), 'test2'], 2],
                ['usualFunc', 0]
            ],

            SecondEvent::class => [
                [[ArrayCallable::class, 'test3'], 0]
            ],

            stdClass::class => [
                [$this->closure, 0]
            ]
        ];

        $listeners = new ReflectionProperty($this->provider, 'listeners');
        $listeners->setAccessible(true);

        self::assertEquals($expected, $listeners->getValue($this->provider));
    }

    public function testNoGivenArgumentsInConstructor()
    {
        $provider = new ReflectionListenerProvider();

        $listeners = new ReflectionProperty($provider, 'listeners');
        $listeners->setAccessible(true);

        self::assertEmpty($listeners->getValue($provider));
    }

    public function wrongListenerTypes(): array
    {
        return [
            [InvokableClass::class],
            [[InvokableClass::class]],
            [34],
            [true],
            [new stdClass()],
        ];
    }

    /**
     * @dataProvider wrongListenerTypes
     * @var mixed $listener
     */
    public function testWrongListenerTypeInConstructor($listener)
    {
        $this->expectErrorMessage('Wrong type of the listener');

        new ReflectionListenerProvider([$listener]);
    }

    public function testGettingOfListeners()
    {
        $providerWithIterator = new ReflectionListenerProvider(new ArrayIterator($this->listenersDefinition));

        $expectedListeners = [
            [new ArrayCallable(), 'test2'],
            new InvokableClass(),
            [ArrayCallable::class, 'test'],
            'usualFunc'
        ];

        self::assertEquals($expectedListeners, $this->provider->getListenersForEvent(new FirstEvent()));
        self::assertEquals($expectedListeners, $providerWithIterator->getListenersForEvent(new FirstEvent()));

        $expectedListeners = [
            [ArrayCallable::class, 'test3']
        ];

        self::assertEquals($expectedListeners, $this->provider->getListenersForEvent(new SecondEvent()));
        self::assertEquals($expectedListeners, $providerWithIterator->getListenersForEvent(new SecondEvent()));

        $expectedListeners = [
            $this->closure
        ];

        self::assertEquals($expectedListeners, $this->provider->getListenersForEvent(new stdClass()));
        self::assertEquals($expectedListeners, $providerWithIterator->getListenersForEvent(new stdClass()));
    }

    public function testAddMethod()
    {
        $provider = new ReflectionListenerProvider();

        $provider->add(new InvokableClass());

        self::assertCount(1, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertInstanceOf(InvokableClass::class, $result[0]);

        $provider->add([new ArrayCallable(), 'test2'], 1);

        self::assertCount(2, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertIsArray($result[0]);

        $provider->add('usualFunc', 2);
        self::assertCount(3, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertEquals('usualFunc', $result[0]);

        $provider->add([new ArrayCallable(), 'test3']);
        self::assertCount(1, $provider->getListenersForEvent(new SecondEvent()));
        self::assertIsArray($provider->getListenersForEvent(new SecondEvent())[0]);
    }

    public function testRemoveMethod()
    {
        self::assertCount(4, $this->provider->getListenersForEvent(new FirstEvent()));
        self::assertCount(1, $this->provider->getListenersForEvent(new stdClass()));

        $this->provider->remove(new InvokableClass());
        $this->provider->remove('usualFunc');
        $this->provider->remove([ArrayCallable::class, 'test']);

        self::assertCount(1, $this->provider->getListenersForEvent(new FirstEvent()));

        $this->provider->remove($this->closure);
        self::assertEmpty($this->provider->getListenersForEvent(new stdClass()));
    }

    public function testNotFoundListenersInRemoveMethod()
    {
        $closure = function (SecondEvent $event): void {};

        $this->expectException(NotFoundListenersException::class);

        $this->provider->remove($closure);
    }
}