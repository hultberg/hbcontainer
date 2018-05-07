<?php declare(strict_types=1);

namespace HbLib\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface, FactoryInterface
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
        $this->definitions[FactoryInterface::class] = $this;
    }

    /**
     * Finds an entry of the container by its identifier and returns it. This will provide singleton instances.
     *
     * @see make()
     * 
     * @param string $id Identifier of the entry to look for.
     *
     * @throws ContainerExceptionInterface
     * @throws NotFoundExceptionInterface
     *
     * @return mixed Entry.
     */
    public function get($id)
    {
        if (!isset($this->singletons[$id])) {
            // Attempt to build the $id class
            $this->singletons[$id] = $this->make($id);
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
            $classInstance = null;

            if (is_string($class)) {
                $classInstance = $this->make($class);
            } else if (is_object($class)) {
                $classInstance = clone $class;
            } else {
                throw new InvokeException('Unable to invoke non-object instance.');
            }
            
            // $this->make() has already verified if class exists when its a string.
            $reflectionClass = new \ReflectionClass($class);

            try {
                $method = $reflectionClass->getMethod($method);
            } catch (\ReflectionException $e) {
                throw new InvokeException('Method ' . $method . ' does not exist on class', 0, $e);
            }

            return $method->invokeArgs($classInstance, $this->resolveParameters($method, $parameters));
        }

        if (is_callable($callable) || (!is_string($callable) && $callable instanceof \Closure) || (is_string($callable) && function_exists($callable))) {
            $reflectionFunction = new \ReflectionFunction($callable);
            return $reflectionFunction->invokeArgs($this->resolveParameters($reflectionFunction, $parameters));
        }

        throw new InvokeException('Unsupported format.');
    }

    /**
     * Build a class without cache.
     *
     * @see get()
     * 
     * @param string $id
     * @param array $parameters
     *
     * @return mixed
     *
     * @throws NotFoundExceptionInterface
     * @throws UnresolvedContainerException
     */
    public function make(string $id, array $parameters = array())
    {
        if (array_key_exists($id, $this->definitions)) {
            $factory = $this->definitions[$id];

            if (\is_callable($factory)) {
                // de jure, this can throw ReflectionException. 
                // de facto, this should not happen.
                $reflectionFunction = new \ReflectionFunction($factory);
                return $reflectionFunction->invokeArgs($this->resolveParameters($reflectionFunction, $parameters));
            }

            // Return whatever...
            return $factory;
        }

        try {
            $reflection = new \ReflectionClass($id);
        } catch (\ReflectionException $e) {
            throw new NotFoundException($e->getMessage(), 0, $e);
        }
        
        $constructor = $reflection->getConstructor();
        
        if ($reflection->isInterface()) {
            throw new UnresolvedContainerException('Cant create an instance of an interface.');
        }
        
        if ($reflection->isAbstract()) {
            throw new UnresolvedContainerException('Cant create an instance of an abstract class.');
        }

        if ($constructor === null) {
            return new $id(); // No constructor.
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
                if ($type !== null && !$type->isBuiltin() && $this->classNameExists($type->getName())) {
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
     * Determines if a classname exists. Able to handle interface, traits and classes.
     *  
     * @param string $className 
     *
     * @return bool   
     */
    private function classNameExists($className): bool
    {
        try {
            new \ReflectionClass($className);
        } catch (\ReflectionException $e) {
            return false;
        }
        
        return true;
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
        try {
            $this->make($id);
        } catch (\ReflectionException|ContainerException $e) {
            return false;
        }
        
        return true;
    }
}
