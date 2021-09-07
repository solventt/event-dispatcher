<?php

declare(strict_types=1);

namespace Slim\EventDispatcher\Tests\Mocks\Listeners;

use Slim\EventDispatcher\Tests\Mocks\Events\FirstEvent;

class InvokableClass
{
    public function __invoke(FirstEvent $event): void
    {
        $event->result .= '-Second';
    }
}