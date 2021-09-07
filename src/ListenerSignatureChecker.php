<?php

declare(strict_types=1);

namespace Solventt\EventDispatcher;

use Closure;
use LengthException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use TypeError;

class ListenerSignatureChecker
{
    private bool $whetherToCheck;

    public function __construct(bool $whetherToCheck = true)
    {
        $this->whetherToCheck = $whetherToCheck;
    }

    /**
     * @param callable $callable
     * @return ReflectionFunctionAbstract
     * @throws ReflectionException
     */
    public function createCallableReflection(callable $callable): ReflectionFunctionAbstract
    {
        if ($callable instanceof Closure || is_string($callable)) {
            return new ReflectionFunction($callable);
        }

        if (is_array($callable)) {
            return new ReflectionMethod(...$callable);
        }

        return new ReflectionMethod($callable, '__invoke');
    }

    /**
     * Checks a listener signature according to the PSR-14 listener requirements
     * @param callable $callable
     * @throws ReflectionException|LengthException|TypeError
     */
    public function check(callable $callable): void
    {
        if (!$this->whetherToCheck) {
            return;
        }

        $reflection = $this->createCallableReflection($callable);

        $this->checkParametersQuantity($reflection);
        $this->checkParameterTypeHint($reflection, 'checkParamTypeIsClassOrObject');
        $this->checkReturnType($reflection);
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @throws LengthException
     */
    private function checkParametersQuantity(ReflectionFunctionAbstract $reflection): void
    {
        $paramsCount = $reflection->getNumberOfParameters();

        if ($paramsCount > 1 || $paramsCount === 0) {
            throw new LengthException('The listener callback must have only one parameter');
        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @param string $method
     * @throws TypeError
     */
    private function checkParameterTypeHint(ReflectionFunctionAbstract $reflection, string $method): void
    {
        $paramType = $reflection->getParameters()[0]->getType();

        if ($paramType === null) {
            throw new TypeError('The type of the listener callback parameter is undefined');
        }

        $this->$method($paramType);
    }

    /**
     * @param ReflectionNamedType $paramType
     * @throws TypeError
     */
    private function checkParamTypeIsClassOrObject(ReflectionNamedType $paramType): void
    {
        if (!class_exists($paramType->getName()) && $paramType->getName() !== 'object') {
            throw new TypeError('The listener parameter must have an object or existent event class type');
        }
    }

    /**
     * @param ReflectionNamedType $paramType
     * @throws TypeError
     */
    private function checkParamTypeIsClass(ReflectionNamedType $paramType): void
    {
        if (!class_exists($paramType->getName())) {
            throw new TypeError('The listener parameter must have a type of an existing event class');
        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @throws TypeError
     */
    private function checkReturnType(ReflectionFunctionAbstract $reflection): void
    {
        $returnType = $reflection->getReturnType();

        if ($returnType->getName() !== 'void') {
            throw new TypeError("The listener callback must have only a 'void' return type");
        }
    }

    /**
     * Gives a listener event (or events) class name
     * @param ReflectionFunctionAbstract $reflection
     * @return string
     */
    public function getEventClassName(ReflectionFunctionAbstract $reflection): string
    {
        $this->checkParametersQuantity($reflection);
        $this->checkParameterTypeHint($reflection, 'checkParamTypeIsClass');
        $this->checkReturnType($reflection);

        $paramType = $reflection->getParameters()[0]->getType();

        return $paramType->getName();
    }
}