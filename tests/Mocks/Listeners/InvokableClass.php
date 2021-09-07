<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher\Tests\Mocks\Listeners;

use Solventt\EventDispatcher\Tests\Mocks\Events\FirstEvent;

class InvokableClass
{
    public function __invoke(FirstEvent $event): void
    {
        $event->result .= '-Second';
    }
}