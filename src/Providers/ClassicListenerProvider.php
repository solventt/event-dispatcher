<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher\Providers;

use ArrayAccess;
use Psr\EventDispatcher\ListenerProviderInterface;
use ReflectionException;
use Solventt\EventDispatcher\Exceptions\NotFoundListenersException;
use Solventt\EventDispatcher\ListenerSignatureChecker;
use Solventt\EventDispatcher\SubscribingInterface;
use TypeError;

class ClassicListenerProvider implements ListenerProviderInterface, SubscribingInterface
{
    /** @var  ArrayAccess|array{class-string: array} */
    private iterable $listeners;

    private ListenerSignatureChecker $listenerChecker;

    /**
     * Incoming array values are filtered.
     * If a listener execution priority is not specified
     * a default priority value will be assigned to it.
     *
     * @param iterable $definition if not empty it must contain associative array(s):
     * [
     *    FirstEvent::class => [... listeners ...]
     *    SecondEvent::class => [... listeners ...]
     * ]
     *
     * Listeners can be given in two formats:
     *
     * 1) new InvokableClass(),               // any callable
     * 2) [[new ArrayCallable(), 'test2'], 2] // or an array containing any callable and its execution priority
     *
     * @param bool $whetherToCheck indicates whether to check a listener signature
     */
    public function __construct(iterable $definition = [], bool $whetherToCheck = true)
    {
        foreach ($definition as &$listenersArray) {
            foreach ($listenersArray as &$listener) {
                if (!is_callable($listener) && !is_array($listener)) {
                    throw new TypeError('Wrong type of the listener');
                }

                if (is_callable($listener)) {
                    $listener = [$listener, SubscribingInterface::DEFAULT_PRIORITY];
                    continue;
                }

                if (!empty($listener) && is_callable($listener[0])) {
                    if (!isset($listener[1]) || !is_integer($listener[1])) {
                        $listener[1] = SubscribingInterface::DEFAULT_PRIORITY;
                    }
                } else {
                    throw new TypeError('Wrong type of the listener');
                }
            }
        }

        $this->listeners = $definition;
        $this->listenerChecker = new ListenerSignatureChecker($whetherToCheck);
    }

    /**
     * @inheritDoc
     * @throws NotFoundListenersException|ReflectionException
     */
    public function getListenersForEvent(object $event): iterable
    {
        $listeners = $this->listeners[get_class($event)] ?? throw new NotFoundListenersException(sprintf('There are no listeners for %s', get_class($event)));

        if (!is_iterable($listeners)) {
            throw new TypeError('Wrong listeners definition format. Iterable is needed');
        }

        $this->sortWithPriority($listeners);

        /** @var array {callable, int} $listener */
        return array_map(function (array $listener): callable {

            $this->listenerChecker->check($listener[0]);

            return $listener[0];
        }, $listeners);
    }

    /**
     * Descending sorting of listeners
     * @param array[] $listeners
     */
    private function sortWithPriority(array &$listeners): void
    {
        usort($listeners, fn(array $a, array $b) => $a[1] == $b[1] ? 0 : ($a[1] > $b[1] ? -1 : 1));
    }

    /** @inheritDoc */
    public function on(string $eventClass, callable $listener, int $priority): void
    {
        $this->listeners[$eventClass][] = [$listener, $priority];
    }

    /**
     * @inheritDoc
     * @throws NotFoundListenersException
     */
    public function off(string $eventClass, callable $listener): void
    {
        if (!isset($this->listeners[$eventClass])) {
            throw new NotFoundListenersException(sprintf('There are no listeners for %s', $eventClass));
        }

        /** @var array {callable, int} $listener */
        $listeners = array_map(fn(array $listener): callable => $listener[0], $this->listeners[$eventClass]);

        foreach ($listeners as $index => $activeListener) {
            /** @psalm-suppress TypeDoesNotContainType $activeListener */
            if ($activeListener == $listener) {
                    unset($this->listeners[$eventClass][$index]);
            }
        }
    }
}