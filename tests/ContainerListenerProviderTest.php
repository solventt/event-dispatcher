<?php

declare(strict_types=1);

namespace Slim\EventDispatcher\Tests;

use ArrayIterator;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionException;
use Slim\EventDispatcher\Exceptions\ClassNotFoundException;
use Slim\EventDispatcher\Exceptions\NotFoundListenersException;
use Slim\EventDispatcher\Providers\ContainerListenerProvider;
use Slim\EventDispatcher\Tests\Mocks\Events\FirstEvent;
use Slim\EventDispatcher\Tests\Mocks\Events\SecondEvent;
use Slim\EventDispatcher\Tests\Mocks\Listeners\ArrayCallable;
use Slim\EventDispatcher\Tests\Mocks\Listeners\InvokableClass;
use stdClass;

class ContainerListenerProviderTest extends TestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    /**
     * This method was created because:
     * the setUp and the setUpBeforeClass methods are executed only after the 'dataProvider' method, so the variables set in the setUp/setUpBeforeClass methods can't be accessed in the dataProvider' method.
     */
    private function getInvokableClasses(): array
    {
        return [
            new class {
                public function __invoke(FirstEvent $event): void {}
            },
            new class {
                public function __invoke(SecondEvent $event): void {}
            },
            new class {
                public function __invoke(SecondEvent $event): void {}
            },
            new class {
                public function __invoke(stdClass $event): void {}
            }
        ];
    }

    public function getRightDefinitions(): array
    {
        $invokableClasses = $this->getInvokableClasses();

        return [
            [
                [
                    FirstEvent::class => [InvokableClass::class, get_class($invokableClasses[0])],
                    SecondEvent::class => [get_class($invokableClasses[1]), get_class($invokableClasses[2])],
                    stdClass::class => [get_class($invokableClasses[3])]
                ]
            ],

            [[InvokableClass::class, get_class($invokableClasses[0]), get_class($invokableClasses[1]),
              get_class($invokableClasses[2]), get_class($invokableClasses[3])]],

            [
                [
                    FirstEvent::class => [InvokableClass::class],
                    get_class($invokableClasses[0]),
                    get_class($invokableClasses[1]),
                    SecondEvent::class => [get_class($invokableClasses[2])],
                    get_class($invokableClasses[3])
                ]
            ]
        ];
    }

    /**
     * @dataProvider getRightDefinitions
     */
    public function testGettingOfListeners(array $definition)
    {
        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container);

        self::assertCount(2, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertInstanceOf(InvokableClass::class, $result[0]);

        self::assertCount(2, $result = $provider->getListenersForEvent(new SecondEvent()));
        $reflection = new \ReflectionClass($result[0]);

        self::assertTrue($reflection->isAnonymous());
        self::assertCount(1, $provider->getListenersForEvent(new stdClass()));
    }

    /**
     * @dataProvider getRightDefinitions
     */
    public function testGettingOfListenersWithIteratorInsteadOfArray(array $definition)
    {
        $this->container->set('eventsToListeners', new ArrayIterator($definition));

        $provider = new ContainerListenerProvider($this->container);

        self::assertCount(2, $result = $provider->getListenersForEvent(new FirstEvent()));
        self::assertInstanceOf(InvokableClass::class, $result[0]);

        self::assertCount(2, $result = $provider->getListenersForEvent(new SecondEvent()));
        $reflection = new \ReflectionClass($result[0]);

        self::assertTrue($reflection->isAnonymous());
        self::assertCount(1, $provider->getListenersForEvent(new stdClass()));
    }

    /**
     * @doesNotPerformAssertions
     */
    public function testGettingOfListenersWithoutSignatureChecking()
    {
        $invokableClass = new class {
            public function __invoke() {}
        };

        $definition = [
            FirstEvent::class => [
                get_class($invokableClass)
            ]
        ];

        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container, 'eventsToListeners', false);

        $provider->getListenersForEvent(new FirstEvent());
    }

    public function testNoDefinitionInContainer()
    {
        $this->expectExceptionMessage('There are no listeners definition');
        new ContainerListenerProvider($this->container);
    }

    public function testEmptyDefinitionInContainer()
    {
        $this->container->set('eventsToListeners', []);

        $provider = new ContainerListenerProvider($this->container);

        $this->expectException(NotFoundListenersException::class);

        $provider->getListenersForEvent(new FirstEvent());
    }

    public function testNotIterableDefinitionFormat()
    {
        $this->container->set('eventsToListeners', InvokableClass::class);

        $this->expectErrorMessage('Wrong listeners definition format. Iterable is needed');

        new ContainerListenerProvider($this->container);
    }

    public function wrongContentForListenersDefinition()
    {
        return [
            [5],
            [true],
            [new InvokableClass()],
            [[ArrayCallable::class, 'test']]
        ];
    }

    /**
     * @dataProvider wrongContentForListenersDefinition
     * @param mixed $value
     */
    public function testWrongContentInListenersDefinition($value)
    {
        $this->container->set('eventsToListeners', [$value]);

        $this->expectErrorMessage('The listeners definition must contain strings or arrays');

        new ContainerListenerProvider($this->container);
    }

    public function testMethodInvokeDoesntExistInConstructor()
    {
        $this->container->set('eventsToListeners', [stdClass::class]);

        $this->expectException(ReflectionException::class);

        new ContainerListenerProvider($this->container);
    }

    public function testEmptyListenersArray()
    {
        $this->container->set('eventsToListeners', [FirstEvent::class => []]);

        $provider = new ContainerListenerProvider($this->container);

        self::assertEquals([], $provider->getListenersForEvent(new FirstEvent()));
    }

    public function invokableListeners(): array
    {
        $closure = function (FirstEvent $event): void {};

        return [
            [new InvokableClass()],
            [[ArrayCallable::class, 'test']],
            [$closure]
        ];
    }

    /**
     * @dataProvider invokableListeners
     */
    public function testIncorrectListenersType(callable $listener)
    {
        $definition = [
            FirstEvent::class => [
                $listener
            ]
        ];

        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container);

        $this->expectError();

        $provider->getListenersForEvent(new FirstEvent());
    }

    public function testDontAcceptUsualFuncWhenStringDefinition()
    {
        $this->container->set('eventsToListeners', ['usualFunc']);

        $this->expectException(ClassNotFoundException::class);

        new ContainerListenerProvider($this->container);
    }

    public function testDontAcceptUsualFuncWhenArrayDefinition()
    {
        $definition = [
            FirstEvent::class => [
                'usualFunc'
            ]
        ];

        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container);

        $this->expectErrorMessage('The listener must be an invokable class, not an usual function');

        $provider->getListenersForEvent(new FirstEvent());
    }

    public function testListenerClassDoesntExist()
    {
        $definition = [
            FirstEvent::class => [
                'NonExistentClass'
            ]
        ];

        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container);

        $this->expectException(ClassNotFoundException::class);

        $provider->getListenersForEvent(new FirstEvent());
    }

    public function testNotInvokableListenerClass()
    {
        $definition = [
            FirstEvent::class => [
                stdClass::class
            ]
        ];

        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container);

        $this->expectError();

        $provider->getListenersForEvent(new FirstEvent());
    }
}