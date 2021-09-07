<?php

declare(strict_types=1);

use Slim\EventDispatcher\Tests\Mocks\Events\FirstEvent;

function usualFunc(FirstEvent $event): void
{
    $event->result .= '-Third';
}