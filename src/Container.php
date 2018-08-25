<?php declare(strict_types=1);

namespace HbLib\Container;

use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface, FactoryInterface, InvokerInterface, ArgumentResolverInterface
{
    /**
     * @var array
     */
    protected $singletons;

    /**
     * @var DefinitionSource
     */
    protected $definitionSource;
    
    /**
     * @var array
     */
    protected $entriesBeingResolved;

    public function __construct(DefinitionSource $definitionSource = null)
    {
        $this->singletons = [];
        $this->entriesBeingResolved = [];
        $this->definitionSource = $definitionSource ?? new DefinitionSource();

        // Register the container itself.
        $this->singletons[self::class] = $this;
        $this->singletons[ContainerInterface::class] = $this;
        $this->singletons[FactoryInterface::class] = $this;
        $this->singletons[InvokerInterface::class] = $this;
        $this->singletons[ArgumentResolverInterface::class] = $this;
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
                $classInstance = $this->get($class);
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

            return $method->invokeArgs($classInstance, $this->resolveArguments($method, $parameters));
        }

        if (is_callable($callable) || (is_string($callable) && function_exists($callable))) {
            $reflectionFunction = new \ReflectionFunction($callable);
            return $reflectionFunction->invokeArgs($this->resolveArguments($reflectionFunction, $parameters));
        }

        throw new InvokeException('Unsupported format.');
    }

    /**
     * Set an instance/value or an definition to the container.
     *
     * @param string $id
     * @param AbstractDefinition|mixed $value
     */
    public function set($id, $value): void
    {
        if ($value instanceof AbstractDefinition) {
            $this->definitionSource->setDefinition($id, $value);
        } else {
            $this->singletons[$id] = $value;
        }
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
        $definition = $this->definitionSource->getDefinition($id);
        
        if (isset($this->entriesBeingResolved[$id])) {
            throw new CircularDependencyException('Circular dependency detected while resolving entry ' . $id);
        }
        $this->entriesBeingResolved[$id] = true;
        
        try {
            if ($definition !== null) {
                if ($definition instanceof AbstractDefinition) {
                    return $this->resolveDefinition($definition, $id);
                }
            
                if (is_callable($definition)) {
                    return $this->call($definition);
                }

                // Return whatever...
                return $definition;
            }

            return $this->resolveClass($id, $parameters);
        } finally {
            unset($this->entriesBeingResolved[$id]);
        }
    }

    private function resolveDefinition(AbstractDefinition $definition, $requestedId)
    {
        if ($definition instanceof DefinitionFactory) {
            // TODO: We should just use call_user_func_array here.
            $function = new \ReflectionFunction($definition->getClosure());
            return $function->invokeArgs($this->resolveArguments($function, $definition->getParameters()));
        }

        if ($definition instanceof DefinitionClass) {
            $className = $definition->getClassName() ?? $requestedId;

            if (count($definition->getParameters()) > 0) {
                // There are specific parameters on this class definition, don't use any singleton cache.
                return $this->resolveClass($className, $definition->getParameters());
            }

            // No parameters, we can just "get" the class.
            return $this->resolveClass($className);
        }
        
        if ($definition instanceof DefinitionReference) {
            return $this->get($definition->getClassName());
        }

        // @codeCoverageIgnoreStart
        throw new \Exception('Unsupported definition');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Construct a class instance and resolve all parameters not provided in the parameters array.
     * 
     * @param string $className  
     * @param array  $parameters 
     * @return mixed
     */
    private function resolveClass(string $className, array $parameters = [])
    {
        try {
            $reflection = new \ReflectionClass($className);
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
            return new $className(); // No constructor.
        }

        return $reflection->newInstanceArgs(array_values($this->resolveArguments($constructor, $parameters)));
    }

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
    public function resolveArguments(\ReflectionFunctionAbstract $function, array $arguments = array()): array
    {
        $resolvedParameters = array();

        foreach ($function->getParameters() as $parameter) {
            // Identify if the type is a class we can attempt to build.
            $type = $parameter->getType();

            if (array_key_exists($parameter->getName(), $arguments)) {
                $parameterValue = $arguments[$parameter->getName()];

                if ($parameterValue instanceof AbstractDefinition) {
                    $parameterValue = $this->resolveDefinition($parameterValue, $parameter->getName());
                }

                $resolvedParameters[$parameter->getName()] = $parameterValue;
                continue;
            } else {
                if ($type !== null && !$type->isBuiltin() && $this->classNameExists($type->getName())) {
                    // We need to resolve a class. In php you cant pass a default class instance AFAIK
                    try {
                        $resolvedParameters[$parameter->getName()] = $this->get($type->getName());
                        continue;
                    } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
                        if (!$parameter->isOptional() && !$parameter->allowsNull()) {
                            $declaringClass = $parameter->getDeclaringClass();
                            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . ($declaringClass ? $declaringClass->getName() : 'N/A'), 0, $e);
                        }

                        $resolvedParameters[$parameter->getName()] = null;
                        continue;
                    }
                }

                // Next is the default value.
                if ($parameter->isOptional() && $parameter->isDefaultValueAvailable()) {
                    // Some builtin with a default value.
                    $resolvedParameters[$parameter->getName()] = $parameter->getDefaultValue();
                    continue;
                }
            }

            $declaringClass = $parameter->getDeclaringClass();
            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . ($declaringClass ? $declaringClass->getName() : 'N/A'));
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
        return $this->definitionSource->hasDefinition($id);
    }
}
