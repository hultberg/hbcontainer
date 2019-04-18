<?php declare(strict_types=1);

namespace HbLib\Container;

use Ds\Map;

interface InvokerInterface
{
    /**
     * Call the given function using the given parameters.
     *
     * @param callable|array|string|\Closure $callable Function to call.
     * @param array $parameters Parameters to use.
     *
     * @return mixed Result of the function.
     *
     * @throws InvokeException Base exception class for all the sub-exceptions below.
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    public function call($callable, array $parameters = []);
}
