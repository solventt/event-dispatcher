<?php

declare(strict_types=1);

namespace Slim\EventDispatcher\Tests\Mocks\Events;

use Psr\EventDispatcher\StoppableEventInterface;

class FirstEvent implements StoppableEventInterface
{
    public string $result = '';

    public function isPropagationStopped(): bool
    {
        return (bool) preg_match('/stop/', $this->result);
    }
}