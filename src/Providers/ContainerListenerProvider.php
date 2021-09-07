<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher\Providers;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;
use Solventt\EventDispatcher\Exceptions\ClassNotFoundException;
use Solventt\EventDispatcher\Exceptions\NotFoundListenersException;
use Solventt\EventDispatcher\ListenerSignatureChecker;
use TypeError;

class ContainerListenerProvider implements ListenerProviderInterface
{
    private array $listeners;

    private ListenerSignatureChecker $listenerChecker;
    private ContainerInterface $container;

    /**
     * @param ContainerInterface $container
     * @param string $definitionName listeners definition name for the DI container
     * @param bool $whetherToCheck indicates whether to check a listener signature
     * @throws NotFoundListenersException|ReflectionException|ClassNotFoundException
     */
    public function __construct(ContainerInterface $container,
                                string $definitionName = 'eventsToListeners',
                                bool $whetherToCheck = true)
    {
        $this->container = $container;
        $this->listenerChecker = new ListenerSignatureChecker($whetherToCheck);

        if (!$container->has($definitionName)) {
            throw new NotFoundListenersException('There are no listeners definition');
        }

        $listeners = $container->get('eventsToListeners');

        if (!is_iterable($listeners)) {
            throw new TypeError('Wrong listeners definition format. Iterable is needed');
        }

        foreach ($listeners as $eventClass => $listener) {

            if (is_array($listener) && !is_callable($listener)) {
                if (!isset($this->listeners[$eventClass])) {
                    $this->listeners[$eventClass] = $listener;
                } else {
                    array_push($this->listeners[$eventClass], ...$listener);
                }
                continue;
            }

            if (!is_string($listener)) {
                throw new TypeError('The listeners definition must contain strings or arrays');
            }

            if (!class_exists($listener)) {
                throw new ClassNotFoundException(sprintf('Class (%s) does not exist', $listener));
            }

            $eventClass = $this->resolveListenerEvent($listener);

            $this->listeners[$eventClass][] = $listener . 'recognizedLabel';
        }
    }

    /**
     * @inheritDoc
     * @throws NotFoundListenersException|ClassNotFoundException|TypeError|ReflectionException
     */
    public function getListenersForEvent(object $event): iterable
    {
        if (!isset($this->listeners[get_class($event)])) {
            throw new NotFoundListenersException(sprintf('There are no listeners for %s', get_class($event)));
        }

        $listeners = $this->listeners[get_class($event)];

        return array_map(function (string $listener) {

            if (preg_match('/recognizedLabel/', $listener, $matches, PREG_OFFSET_CAPTURE)) {
                $position = $matches[0][1];

                /** @var class-string $listener */
                $listener = substr($listener, 0, $position);

                return $this->container->get($listener);
            }

            if (is_callable($listener)) {
                throw new TypeError('The listener must be an invokable class, not an usual function');
            }

            if (class_exists($listener)) {
                $listener = $this->container->get($listener);
            } else {
                throw new ClassNotFoundException(sprintf('Class (%s) does not exist', $listener));
            }

            if (!is_callable($listener)) {
                throw new TypeError(sprintf('The listener must be callable. For this, the class %s must implements the __invoke method', get_class($listener)));
            }

            $this->listenerChecker->check($listener);

            return $listener;
        }, $listeners);
    }

    /**
     * @param class-string $listener
     * @throws ReflectionException
     */
    private function resolveListenerEvent(string $listener): string
    {
        $reflection = new \ReflectionClass($listener);

        try {
            $method = $reflection->getMethod('__invoke');
        } catch (ReflectionException $e) {
            throw new ReflectionException(sprintf($e->getMessage() . ' in %s', $listener));
        }

        return $this->listenerChecker->getEventClassName($method);
    }
}