<?php

namespace Slim\EventDispatcher;

interface SubscribingInterface
{
    /** Default value for listener execution priority */
    const DEFAULT_PRIORITY = 0;

    /**
     * Binds a listener to an event
     * @param string $eventClass
     * @param callable $listener
     * @param int $priority
     */
    public function on(string $eventClass, callable $listener, int $priority): void;

    /**
     * Unbinds a listener from an event
     * @param string $eventClass
     * @param callable $listener
     */
    public function off(string $eventClass, callable $listener): void;
}