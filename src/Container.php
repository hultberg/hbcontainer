<?php declare(strict_types=1);

namespace HbLib\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface
{
    /**
     * @var array
     */
    private $singletons;

    /**
     * @var array
     */
    private $definitions;

    public function __construct(array $definitions = array())
    {
        $this->singletons = array();
        $this->definitions = $definitions;

        // Register the container itself.
        $this->singletons[self::class] = $this;
        $this->singletons[ContainerInterface::class] = $this;
    }

    /**
     * Finds an entry of the container by its identifier and returns it.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @throws NotFoundExceptionInterface  No entry was found for **this** identifier.
     * @throws ContainerExceptionInterface Error while retrieving the entry.
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        return $this->make($id);
    }

    /**
     * @param string $id
     * @param array $parameters
     *
     * @return object
     *
     * @throws UnresolvedContainerException
     */
    public function make(string $id, array $parameters = array()): object
    {
        if (!isset($this->singletons[$id])) {
            // Attempt to build the $id class
            try {
                $this->singletons[$id] = $this->build($id, $parameters);
            } catch (\ReflectionException $e) {
                throw new UnresolvedContainerException('Error while resolving ' . $id, 0, $e);
            } catch (UnresolvedContainerException $e) {
                throw new UnresolvedContainerException('Unable to resolve ' . $id, 0, $e);
            }
        }

        return $this->singletons[$id];
    }

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
    public function call($callable, array $parameters = array())
    {
        if (is_array($callable)) {
            list($class, $method) = $callable;
            $reflectionClass = new \ReflectionClass($class);
            $classInstance = null;

            if (is_string($class)) {
                $classInstance = $this->make($class);
            } else if (is_object($class)) {
                $classInstance = clone $class;
            } else {
                throw new InvokeException('Unable to invoke non-object instance.');
            }

            try {
                $method = $reflectionClass->getMethod($method);
            } catch (\ReflectionException $e) {
                throw new InvokeException('Method ' . $method . ' does not exist on class', 0, $e);
            }

            return $method->invokeArgs($classInstance, $this->resolveParameters($method, $parameters));
        }

        if (is_callable($callable) || $callable instanceof \Closure || (is_string($callable) && function_exists($callable))) {
            $reflectionFunction = new \ReflectionFunction($callable);
            return $reflectionFunction->invokeArgs($this->resolveParameters($reflectionFunction, $parameters));
        }

        throw new InvokeException('Unsupported format.');
    }

    /**
     * Build a class without cache.
     *
     * @param string $className
     * @param array $parameters
     *
     * @return mixed
     *
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    private function build(string $className, array $parameters = array())
    {
        if (array_key_exists($className, $this->definitions)) {
            $factory = $this->definitions[$className];

            if (!\is_callable($factory)) {
                throw new UnresolvedContainerException("Invalid factory for definition $className");
            }

            $reflectionFunction = new \ReflectionFunction($factory);
            return $reflectionFunction->invokeArgs($this->resolveParameters($reflectionFunction, $parameters));
            //return \call_user_func_array($factory, $this->resolveParameters(new \ReflectionFunction(), $parameters));
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $className(); // No constructor.
        }

        return $reflection->newInstanceArgs($this->resolveParameters($constructor, $parameters));
    }

    /**
     * Resolve parameters of a reflection function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param array $parameters
     *
     * @return array
     *
     * @throws UnresolvedContainerException
     */
    public function resolveParameters(\ReflectionFunctionAbstract $function, array $parameters = array()): array
    {
        $resolvedParameters = array();

        foreach ($function->getParameters() as $parameter) {
            // Identify if the type is a class we can attempt to build.
            $type = $parameter->getType();

            if (array_key_exists($parameter->getName(), $parameters)) {
                $resolvedParameters[$parameter->getName()] = $parameters[$parameter->getName()];
            } else {
                if ($type !== null && !$type->isBuiltin() && class_exists($type->getName())) {
                    try {
                        $resolvedParameters[$parameter->getName()] = $this->make($type->getName());
                        continue;
                    } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
                        if (!$parameter->isOptional() && !$parameter->allowsNull()) {
                            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . ($parameter->getDeclaringClass() ? $parameter->getDeclaringClass()->getName() : 'N/A'), 0, $e);
                        }

                        $resolvedParameters[$parameter->getName()] = null;
                        continue;
                    }
                }

                throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . $parameter->getDeclaringClass()->getName());
            }
        }

        return $resolvedParameters;
    }

    /**
     * Returns true if the container can return an entry for the given identifier.
     * Returns false otherwise.
     *
     * `has($id)` returning true does not mean that `get($id)` will not throw an exception.
     * It does however mean that `get($id)` will not throw a `NotFoundExceptionInterface`.
     *
     * @param string $id Identifier of the entry to look for.
     *
     * @return bool
     */
    public function has($id)
    {
        return array_key_exists($id, $this->singletons);
    }
}
