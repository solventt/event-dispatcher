<?php

declare(strict_types=1);

namespace Slim\EventDispatcher\Tests;

use BadMethodCallException;
use DI\Container;
use PHPUnit\Framework\TestCase;
use Psr\Container\ContainerInterface;
use ReflectionProperty;
use Slim\EventDispatcher\EventDispatcher;
use Slim\EventDispatcher\Exceptions\ClassNotFoundException;
use Slim\EventDispatcher\Exceptions\NotFoundListenersException;
use Slim\EventDispatcher\Providers\ContainerListenerProvider;
use Slim\EventDispatcher\Providers\ClassicListenerProvider;
use Slim\EventDispatcher\Providers\ReflectionListenerProvider;
use Slim\EventDispatcher\Tests\Mocks\Events\FirstEvent;
use Slim\EventDispatcher\Tests\Mocks\Events\SecondEvent;
use Slim\EventDispatcher\Tests\Mocks\Listeners\ArrayCallable;
use Slim\EventDispatcher\Tests\Mocks\Listeners\InvokableClass;

class EventDispatcherTest extends TestCase
{
    private ContainerInterface $container;

    protected function setUp(): void
    {
        $this->container = new Container();
    }

    public function testDispatchWithClassicListenerProvider()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => [
                [ArrayCallable::class, 'test'],
                new InvokableClass(),
                'usualFunc'
            ]
        ]);

        $dispatcher = new EventDispatcher($provider);

        $event = new FirstEvent();

        self::assertEmpty($initialValue = $event->result);

        $notModifiedEvent = $dispatcher->dispatch($event);

        self::assertEquals('First-Second-Third', $event->result);
        self::assertEquals($initialValue, $notModifiedEvent->result);
    }

    public function testDispatchWithContainerListenerProvider()
    {
        $definition = [
            FirstEvent::class => [
                InvokableClass::class
            ]
        ];

        $this->container->set('eventsToListeners', $definition);

        $provider = new ContainerListenerProvider($this->container);
        $dispatcher = new EventDispatcher($provider);

        $dispatcher->dispatch($event = new FirstEvent());

        self::assertEquals('-Second', $event->result);
    }

    public function testNotFoundListeners()
    {
        $dispatcher = new EventDispatcher(new ClassicListenerProvider([]));

        $this->expectException(NotFoundListenersException::class);

        $dispatcher->dispatch(new FirstEvent());
    }

    public function testPropagationStoppedEvent()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => [
                [ArrayCallable::class, 'test'],
                new InvokableClass(),
                [new ArrayCallable(), 'test2'],
                'usualFunc'
            ]
        ]);

        $dispatcher = new EventDispatcher($provider);

        $event = new FirstEvent();
        $initialValue = $event->result;

        $notModifiedEvent = $dispatcher->dispatch($event);

        self::assertEquals('First-Second-stop', $event->result);
        self::assertEquals($initialValue, $notModifiedEvent->result);
    }

    public function testDispatchDeferredEvents()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => [
                new InvokableClass(),
                'usualFunc'
            ],
            SecondEvent::class => [
                [ArrayCallable::class, 'test3']
            ]
        ]);

        $dispatcher = new EventDispatcher($provider);

        $dispatcher->defer($firstEvent = new FirstEvent());
        $dispatcher->defer($secondEvent = new SecondEvent());

        self::assertEmpty($firstEvent->result);
        self::assertEmpty($secondEvent->result);

        $deferredEvents = new ReflectionProperty($dispatcher, 'deferredEvents');
        $deferredEvents->setAccessible(true);

        self::assertCount(2, $deferredEvents->getValue($dispatcher));

        $dispatcher->dispatchDeferredEvents();

        self::assertEquals('-Second-Third', $firstEvent->result);
        self::assertEquals('Test', $secondEvent->result);

        self::assertCount(0, $deferredEvents->getValue($dispatcher));
    }

    public function testOnMethod()
    {
        $provider = new ClassicListenerProvider();
        $dispatcher = new EventDispatcher($provider);

        $dispatcher->on(FirstEvent::class, new InvokableClass());
        $dispatcher->on(SecondEvent::class, [ArrayCallable::class, 'test3']);

        self::assertCount(1, $provider->getListenersForEvent(new FirstEvent()));
        self::assertCount(1, $provider->getListenersForEvent(new SecondEvent()));

        $dispatcher->dispatch($firstEvent = new FirstEvent());
        $dispatcher->dispatch($secondEvent = new SecondEvent());

        self::assertEquals('-Second', $firstEvent->result);
        self::assertEquals('Test', $secondEvent->result);
    }

    public function testOffMethod()
    {
        $provider = new ClassicListenerProvider([
            FirstEvent::class => [
                new InvokableClass(),
                [ArrayCallable::class, 'test'],
            ],
            SecondEvent::class => [
                [ArrayCallable::class, 'test3']
            ]
        ]);

        self::assertCount(2, $provider->getListenersForEvent(new FirstEvent()));
        self::assertCount(1, $provider->getListenersForEvent(new SecondEvent()));

        $dispatcher = new EventDispatcher($provider);

        $dispatcher->off(FirstEvent::class, [ArrayCallable::class, 'test']);
        $dispatcher->off(SecondEvent::class, [ArrayCallable::class, 'test3']);

        self::assertCount(1, $provider->getListenersForEvent(new FirstEvent()));
        self::assertCount(0, $provider->getListenersForEvent(new SecondEvent()));
    }

    public function testUnsupportedMethodFailInOnMethod()
    {
        $provider = new ReflectionListenerProvider();
        $dispatcher = new EventDispatcher($provider);

        $this->expectException(BadMethodCallException::class);

        $dispatcher->on(FirstEvent::class, new InvokableClass());
    }

    public function testUnsupportedMethodFailInAddMethod()
    {
        $provider = new ClassicListenerProvider();
        $dispatcher = new EventDispatcher($provider);

        $this->expectException(BadMethodCallException::class);

        $dispatcher->add(new InvokableClass());
    }

    public function testClassNotFoundFailInOnMethod()
    {
        $provider = new ClassicListenerProvider();
        $dispatcher = new EventDispatcher($provider);

        $this->expectException(ClassNotFoundException::class);

        $dispatcher->on('NonExistentEvent', new InvokableClass());
    }

}