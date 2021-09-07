<?php

declare(strict_types=1);

namespace Slim\EventDispatcher\Tests;

use LengthException;
use PHPUnit\Framework\TestCase;
use Slim\EventDispatcher\ListenerSignatureChecker;
use Slim\EventDispatcher\Tests\Mocks\Events\FirstEvent;
use Slim\EventDispatcher\Tests\Mocks\Listeners\ArrayCallable;
use Slim\EventDispatcher\Tests\Mocks\Listeners\InvokableClass;

class ListenerSignatureCheckerTest extends TestCase
{
    public function listeners(): array
    {
        $closure = function (FirstEvent $event): void {};

        return [
            [new InvokableClass()],
            [[ArrayCallable::class, 'test']],
            [[new ArrayCallable(), 'test2']],
            [[ArrayCallable::class, 'test4']],
            [$closure],
            ['usualFunc']
        ];
    }

    /**
     * @dataProvider listeners
     * @doesNotPerformAssertions
     */
    public function testChecking(callable $listener)
    {
        (new ListenerSignatureChecker())->check($listener);
    }

    public function testListenerHasNoParameters()
    {
        self::expectException(LengthException::class);

        (new ListenerSignatureChecker())->check([ArrayCallable::class, 'noParameters']);
    }

    public function testListenerHasMoreThanOneParameter()
    {
        self::expectException(LengthException::class);

        (new ListenerSignatureChecker())->check([new ArrayCallable(), 'moreThanOneParameter']);
    }

    public function testUndefinedListenerParameterType()
    {
        self::expectErrorMessage('The type of the listener callback parameter is undefined');

        (new ListenerSignatureChecker())->check([ArrayCallable::class, 'undefinedParameterType']);
    }

    public function testListenerParameterTypeIsNotClassOrObject()
    {
        $closure = fn(string $param) => '';

        self::expectErrorMessage('The listener parameter must has object or existent event class type');

        (new ListenerSignatureChecker())->check($closure);
    }

    public function testWrongListenerReturnType()
    {
        self::expectErrorMessage("The listener callback must have only 'void' return type");

        (new ListenerSignatureChecker())->check([new ArrayCallable(), 'wrongReturnType']);
    }
}