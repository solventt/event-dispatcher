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
use ReflectionUnionType;
use TypeError;

class ListenerSignatureChecker
{
    public function __construct(private bool $whetherToCheck = true){}

    /**
     * @param callable|class-string $listener
     * @return ReflectionFunctionAbstract
     * @throws ReflectionException
     */
    public function createListenerReflection(callable|string $listener): ReflectionFunctionAbstract
    {
        if (!is_callable($listener)) {
            $reflection = new \ReflectionClass($listener);

            try {
                $method = $reflection->getMethod('__invoke');
            } catch (ReflectionException $e) {
                throw new ReflectionException(sprintf($e->getMessage() . ' in %s', $listener));
            }
            return $method;
        }

        if ($listener instanceof Closure || is_string($listener)) {
            return new ReflectionFunction($listener);
        }

        if (is_array($listener)) {
            return new ReflectionMethod(...$listener);
        }

        return new ReflectionMethod($listener, '__invoke');
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

        $reflection = $this->createListenerReflection($callable);

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

        if ($paramType instanceof ReflectionUnionType) {
            foreach ($paramType->getTypes() as $type) {
                $this->$method($type);
            }
        } else {
            $this->$method($paramType);
        }
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

        if (!$returnType || $returnType instanceof ReflectionUnionType || $returnType->getName() !== 'void') {
            throw new TypeError("The listener callback must have only a 'void' return type");
        }
    }

    /**
     * Gives a listener event (or events) class name
     * @param callable|class-string $listener
     * @return array
     * @throws ReflectionException
     */
    public function getEventClassName(callable|string $listener): array
    {
        $reflection = $this->createListenerReflection($listener);

        $this->checkParametersQuantity($reflection);
        $this->checkParameterTypeHint($reflection, 'checkParamTypeIsClass');
        $this->checkReturnType($reflection);

        $paramType = $reflection->getParameters()[0]->getType();

        if ($paramType instanceof ReflectionUnionType) {
            $types = [];

            foreach ($paramType->getTypes() as $type) {
                $types[] = $type->getName();
            }

            return $types;
        } else {
            return [$paramType->getName()];
        }
    }
}