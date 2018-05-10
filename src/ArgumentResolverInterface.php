<?php declare(strict_types=1);

namespace HbLib\Container;

interface ArgumentResolverInterface
{
    /**
     * Resolve parameters of a reflection function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param array $arguments
     *
     * @return array
     *
     * @throws UnresolvedContainerException
     */
    public function resolveArguments(\ReflectionFunctionAbstract $function, array $arguments = array()): array;
}
