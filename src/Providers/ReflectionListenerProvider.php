<?php

namespace Solventt\EventDispatcher\Providers;

use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;
use Solventt\EventDispatcher\Exceptions\NotFoundListenersException;
use Solventt\EventDispatcher\ListenerSignatureChecker;
use Solventt\EventDispatcher\SubscribingInterface;
use TypeError;

class ReflectionListenerProvider implements ListenerProviderInterface
{
    private iterable $listeners;

    /**
     * Incoming array values are filtered.
     * Given listeners are bound to events specified in their parameter's type-hint
     * If a listener execution priority is not specified
     * a default priority value will be assigned to it.
     *
     * @param iterable $definition contains listeners which can be given in two formats:
     *
     * 1) new InvokableClass(),               // any callable
     * 2) [[new ArrayCallable(), 'test2'], 2] // or an array containing any callable and its execution priority
     *
     * @throws ReflectionException
     */
    public function __construct(iterable $definition = [])
    {
        foreach ($definition as &$listener) {
            $priority = SubscribingInterface::DEFAULT_PRIORITY;

            if (!is_callable($listener) && !is_array($listener)) {
                throw new TypeError('Wrong type of the listener');
            }
            /** @var  callable|array{callable, int} $listener */
            if (is_array($listener) && is_callable($listener[0])) {
                if (isset($listener[1]) && is_integer($listener[1])) {
                    [$listener, $priority] = $listener;
                } else {
                    $listener = $listener[0];
                }
            }

            if (!is_callable($listener) && !is_callable($listener[0])) {
                throw new TypeError('Wrong type of the listener');
            }
            /** @var callable $listener */
            $this->add($listener, $priority);
        }

        if (!isset($this->listeners)) {
            $this->listeners = $definition;
        }
    }

    /* @inheritDoc */
    public function getListenersForEvent(object $event): iterable
    {
        return (new ClassicListenerProvider($this->listeners, false))->getListenersForEvent($event);
    }


    /**
     * Resolves a listener event (or events) based on its parameter's type-hint
     * and adds a listener in the storage.
     * @param callable $listener
     * @param int $priority
     * @throws ReflectionException
     */
    public function add(callable $listener, int $priority = SubscribingInterface::DEFAULT_PRIORITY): void
    {
        $eventsClasses = (new ListenerSignatureChecker())->getEventClassName($listener);


        foreach ($eventsClasses as $eventClass) {
            $this->listeners[$eventClass][] = [$listener, $priority];
        }
    }

    /**
     * Removes a listener (if exists) from the storage.
     * @param callable $listener
     * @throws NotFoundListenersException
     */
    public function remove(callable $listener): void
    {
        $existence = false;

        foreach ($this->listeners as &$listenersArray) {
            foreach ($listenersArray as $index => $activeListener) {
                if ($activeListener[0] == $listener) {
                    unset($listenersArray[$index]);
                    $existence = true;
                }
            }
        }

        if (!$existence) {
            throw new NotFoundListenersException('The listener is not found');
        }
    }
}
