<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher;

use BadMethodCallException;
use LogicException;
use Psr\EventDispatcher\EventDispatcherInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use Psr\EventDispatcher\StoppableEventInterface;
use Solventt\EventDispatcher\Exceptions\ClassNotFoundException;
use Solventt\EventDispatcher\Exceptions\NotFoundListenersException;
use Solventt\EventDispatcher\Providers\ReflectionListenerProvider;

class EventDispatcher implements EventDispatcherInterface
{
    private ListenerProviderInterface $provider;

    /** Storage for deferred events */
    private array $deferredEvents;

    public function __construct(ListenerProviderInterface $provider)
    {
        $this->provider = $provider;
    }

    /**
     * @inheritDoc
     * @throws NotFoundListenersException
     */
    public function dispatch(object $event): object
    {
        $immutableEventInstance = clone $event;

        $listeners = $this->provider->getListenersForEvent($event);

        if (!$listeners) {
            throw new NotFoundListenersException('No listeners are defined for this event');
        }

        foreach ($listeners as $listener) {
            if ($event instanceof StoppableEventInterface) {
                if ($event->isPropagationStopped()) {
                    return $immutableEventInstance;
                }
            }

            $listener($event);
        }
            return $immutableEventInstance;
    }

    public function dispatchDeferredEvents(): void
    {
        if (empty($this->deferredEvents)) {
            throw new LogicException('There are no deferred events');
        }

        foreach ($this->deferredEvents as $event) {
            $this->dispatch($event);
        }

        $this->clearDeferredEvents();
    }

    public function defer(object $event): void
    {
        $this->deferredEvents[] = $event;
    }

    public function clearDeferredEvents(): void
    {
        $this->deferredEvents = [];
    }

    /**
     * Binds a listener to an event
     * @param string $eventClass
     * @param callable $listener
     * @param int $priority listener execution priority
     * @throws BadMethodCallException|ClassNotFoundException
     */
    public function on(
        string $eventClass,
        callable $listener,
        int $priority = SubscribingInterface::DEFAULT_PRIORITY
    ): void
    {
        $this->checkArguments($this->provider, $eventClass, __FUNCTION__);

        /** @var SubscribingInterface $this->provider */
        $this->provider->on($eventClass, $listener, $priority);
    }

    /**
     * Unbinds a listener from an event
     * @param string $eventClass
     * @param callable $listener
     * @throws BadMethodCallException|ClassNotFoundException
     */
    public function off(string $eventClass, callable $listener): void
    {
        $this->checkArguments($this->provider, $eventClass, __FUNCTION__);

        /** @var SubscribingInterface $this->provider */
        $this->provider->off($eventClass, $listener);
    }

    /**
     * @param ListenerProviderInterface $provider
     * @param string|null $eventClass
     * @param string $methodName
     * @throws BadMethodCallException|ClassNotFoundException
     */
    private function checkArguments(
        ListenerProviderInterface $provider,
        ?string $eventClass,
        string $methodName
    ): void
    {
        $branch = in_array($methodName, ['on', 'off']) ? 1 : 2;

        $badMethodCallMessage = sprintf("(%s) does not support the '%s' method", get_class($this->provider), $methodName);

        if ($branch === 1) {
            if (!$provider instanceof SubscribingInterface) {
                throw new BadMethodCallException($badMethodCallMessage);
            }

            if (!class_exists($eventClass)) {
                throw new ClassNotFoundException(sprintf('(%s) does not exist', $eventClass));
            }
        } else {
            if (!$this->provider instanceof ReflectionListenerProvider) {
                throw new BadMethodCallException($badMethodCallMessage);
            }
        }
    }

    /**
     * Adds a listener. This method is supported only by ReflectionListenerProvider
     * @param callable $listener
     * @param int $priority
     * @throws ClassNotFoundException
     */
    public function add(callable $listener, int $priority = SubscribingInterface::DEFAULT_PRIORITY): void
    {
        $this->checkArguments($this->provider, null, __FUNCTION__);

        /** @var ReflectionListenerProvider $this->provider */
        $this->provider->add($listener, $priority);
    }

    /**
     * Removes a listener. This method is supported only by ReflectionListenerProvider
     * @param callable $listener
     * @throws ClassNotFoundException
     */
    public function remove(callable $listener): void
    {
        $this->checkArguments($this->provider, null, __FUNCTION__);

        /** @var ReflectionListenerProvider $this->provider */
        $this->provider->remove($listener);
    }
}