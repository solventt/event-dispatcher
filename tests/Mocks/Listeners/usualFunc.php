<?php

declare(strict_types=1);

use Solventt\EventDispatcher\Tests\Mocks\Events\FirstEvent;

function usualFunc(FirstEvent $event): void
{
    $event->result .= '-Third';
}