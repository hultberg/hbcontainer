<?php declare(strict_types=1);

namespace HbLib\Container;

use Ds\Collection;
use Ds\Map;

interface ArgumentResolverInterface
{
    /**
     * Resolve parameters of a reflection function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param Map|null $arguments
     *
     * @return Collection
     *
     * @throws UnresolvedContainerException
     */
    public function resolve(\ReflectionFunctionAbstract $function, Map $arguments = null): Collection;
}
