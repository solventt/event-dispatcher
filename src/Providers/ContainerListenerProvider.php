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

    /**
     * @param ContainerInterface $container
     * @param string $definitionName listeners definition name for the DI container
     * @param bool $whetherToCheck indicates whether to check a listener signature
     * @throws NotFoundListenersException|ReflectionException|ClassNotFoundException
     */
    public function __construct(private ContainerInterface $container,
                                string $definitionName = 'eventsToListeners',
                                bool $whetherToCheck = true)
    {
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

            $eventsClasses = $this->listenerChecker->getEventClassName($listener);

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
        $listeners = $this->listeners[get_class($event)] ?? throw new NotFoundListenersException(sprintf('There are no listeners for %s', get_class($event)));

        return array_map(function (string $listener) {

            if (str_contains($listener, 'recognizedLabel')) {
                $position = strpos($listener, 'recognizedLabel');

                /** @var class-string $listener */
                $listener = substr($listener, 0, $position ?: null);

                return $this->container->get($listener);
            }

            if (is_callable($listener)) {
                throw new TypeError('The listener must be an invokable class, not an usual function');
            }

            $listener = class_exists($listener) ? $this->container->get($listener) : throw new ClassNotFoundException(sprintf('Class (%s) does not exist', $listener));

            if (!is_callable($listener)) {
                throw new TypeError(sprintf('The listener must be callable. For this, the class %s must implements the __invoke method', get_class($listener)));
            }

            $this->listenerChecker->check($listener);

            return $listener;
        }, $listeners);
    }
}
