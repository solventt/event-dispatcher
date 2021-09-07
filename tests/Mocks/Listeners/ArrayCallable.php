<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher\Tests\Mocks\Listeners;

use Solventt\EventDispatcher\Tests\Mocks\Events\FirstEvent;
use Solventt\EventDispatcher\Tests\Mocks\Events\SecondEvent;

class ArrayCallable
{
    public static function test(FirstEvent $event): void
    {
        $event->result = 'First';
    }

    public function test2(FirstEvent $event): void
    {
        $event->result .= '-stop';
    }

    public static function test3(SecondEvent $event): void
    {
        $event->result = 'Test';
    }

    public static function test4(object $event): void {}

    public static function noParameters(): void {}

    public function moreThanOneParameter(FirstEvent $event, string $name): void {}

    public static function undefinedParameterType($event): void {}

    public function wrongReturnType(FirstEvent $event): string {}

    //public static function paramTypeIsNotObject($event): void {}

}