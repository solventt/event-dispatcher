<?php

declare(strict_types=1);

namespace Slim\EventDispatcher;

use Closure;
use LengthException;
use ReflectionException;
use ReflectionFunction;
use ReflectionFunctionAbstract;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionType;
use ReflectionUnionType;
use TypeError;

class ListenerSignatureChecker
{
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

        if (is_object($callable)) {
            return new ReflectionMethod($callable, '__invoke');
        }
    }

    /**
     * Checks a listener signature according to PSR-14 listener requirements
     * @param callable $callable
     * @throws ReflectionException|LengthException|TypeError
     */
    public function check(callable $callable): void
    {
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
            throw new TypeError('The listener parameter must has object or existent event class type');
        }
    }

    /**
     * @param ReflectionNamedType $paramType
     * @throws TypeError
     */
    private function checkParamTypeIsClass(ReflectionNamedType $paramType): void
    {
        if (!class_exists($paramType->getName())) {
            throw new TypeError('The listener parameter must has only existent event class type');
        }
    }

    /**
     * @param ReflectionFunctionAbstract $reflection
     * @throws TypeError
     */
    private function checkReturnType(ReflectionFunctionAbstract $reflection): void
    {
        $returnType = $reflection->getReturnType();

        if ($returnType instanceof ReflectionUnionType || $returnType->getName() !== 'void') {
            throw new TypeError("The listener callback must have only 'void' return type");
        }
    }

    /**
     * Gives a listener event (or events) class name
     * @param ReflectionFunctionAbstract $reflection
     * @return array
     */
    public function getEventClassName(ReflectionFunctionAbstract $reflection): array
    {
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