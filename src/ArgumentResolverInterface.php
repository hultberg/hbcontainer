<?php declare(strict_types=1);

namespace HbLib\Container;

interface ArgumentResolverInterface
{
    /**
     * Resolve parameters of a reflection function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param array<string, mixed> $arguments
     *
     * @return Argument[]
     *
     * @throws UnresolvedContainerException
     */
    public function resolve(\ReflectionFunctionAbstract $function, array $arguments = []): array;
}
