<?php

declare(strict_types=1);

namespace Slim\EventDispatcher\Providers;

use Psr\Container\ContainerInterface;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;
use Slim\EventDispatcher\Exceptions\ClassNotFoundException;
use Slim\EventDispatcher\Exceptions\NotFoundListenersException;
use Slim\EventDispatcher\ListenerSignatureChecker;
use TypeError;

class ContainerListenerProvider implements ListenerProviderInterface
{
    private array $listeners;

    private bool $whetherToCheck;

    private ListenerSignatureChecker $listenerChecker;

    /**
     * @param ContainerInterface $container
     * @param string $definitionName Listeners definition name for DI container
     * @param bool $whetherToCheck Indicates whether to check a listener signature
     * @throws NotFoundListenersException|ReflectionException|ClassNotFoundException
     */
    public function __construct(ContainerInterface $container,
                                string $definitionName = 'eventsToListeners',
                                bool $whetherToCheck = true)
    {
        $this->listenerChecker = new ListenerSignatureChecker();
        $this->whetherToCheck = $whetherToCheck;

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

            $eventsClasses = $this->resolveListenerEvents($listener);

            foreach ($eventsClasses as $eventClass) {
                $this->listeners[$eventClass][] = $listener . 'recognizedLabel';
            }
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
                $listener = substr($listener, 0, $position);

                return new $listener;
            }

            if (is_callable($listener)) {
                throw new TypeError('The listener must be an invokable class, not an usual function');
            }

            if (class_exists($listener)) {
                $listener = new $listener;
            } else {
                throw new ClassNotFoundException(sprintf('Class (%s) does not exist', $listener));
            }

            if (!is_callable($listener)) {
                throw new TypeError(sprintf('The listener must be callable. For this, the class %s must implements the __invoke method', get_class($listener)));
            }

            if ($this->whetherToCheck) {
                $this->listenerChecker->check($listener);
            }

            return $listener;
        }, $listeners);
    }

    private function resolveListenerEvents(string $listener): array
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