<?php declare(strict_types=1);

namespace HbLib\Container;

use function array_values;
use function count;
use function function_exists;
use function is_array;
use function is_callable;
use function is_object;
use function is_string;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;

class Container implements ContainerInterface, FactoryInterface, InvokerInterface
{
    /**
     * @phpstan-var array<string, ObjectReference|mixed>
     * @var ObjectReference[]|mixed[]
     */
    protected array $singletons;

    /**
     * @var DefinitionSource
     */
    protected DefinitionSource $definitionSource;

    /**
     * @phpstan-var array<string, bool>
     * @var array
     */
    protected array $entriesBeingResolved;

    /**
     * @var ArgumentResolverInterface
     */
    private ArgumentResolverInterface $argumentResolver;

    public function __construct(DefinitionSource $definitionSource = null, ArgumentResolverInterface $argumentResolver = null)
    {
        $this->singletons = [];
        $this->entriesBeingResolved = [];

        $this->definitionSource = $definitionSource ?? new DefinitionSource();
        $this->definitionSource->set(ContainerInterface::class, new DefinitionReference(self::class));
        $this->definitionSource->set(FactoryInterface::class, new DefinitionReference(self::class));
        $this->definitionSource->set(InvokerInterface::class, new DefinitionReference(self::class));

        $this->argumentResolver = $argumentResolver ?? new ArgumentResolver();

        $argumentResolverClassName = get_class($this->argumentResolver);
        $this->definitionSource->set($argumentResolverClassName, new DefinitionValue($this->argumentResolver));
        $this->definitionSource->set(ArgumentResolverInterface::class, new DefinitionReference($argumentResolverClassName));

        // Register the container itself.
        $this->singletons[self::class] = new SingletonReference($this);
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
    public function get(string $id): mixed
    {
        if (isset($this->singletons[$id]) === true) {
            $singletonValue = $this->singletons[$id];

            if ($singletonValue instanceof ObjectReference) {
                $singletonValue = $singletonValue->get();

                if ($singletonValue !== null) {
                    return $singletonValue;
                }

                unset($this->singletons[$id]);
            } else {
                return $singletonValue;
            }
        }

        // Attempt to build the $id class
        $instance = $this->make($id); // this is to keep a strong reference during the method lifetime.
        $this->setSingleton($id, $instance);

        return $instance;
    }

    /**
     * Call the given function using the given parameters.
     *
     * @phpstan-param callable|array{class-string|object, string}|string|\Closure $callable
     * @param callable|array|string|\Closure $callable Function to call.
     * @phpstan-param array<string, mixed> $parameters
     * @param array $parameters Parameters to use.
     *
     * @return mixed Result of the function.
     *
     * @throws InvokeException Base exception class for all the sub-exceptions below.
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    public function call($callable, array $parameters = [])
    {
        if (is_array($callable)) {
            [$class, $method] = $callable;

            // $this->make() has already verified if class exists when its a string.
            $reflectionClass = new \ReflectionClass(is_scalar($class) ? (string) $class : $class);

            try {
                $method = $reflectionClass->getMethod($method);
            } catch (\ReflectionException $e) {
                throw new InvokeException('Method ' . $method . ' does not exist on class', 0, $e);
            }

            if (is_string($class)) {
                $classInstance = $this->get($class);
            } else if (is_object($class)) {
                $classInstance = clone $class;
            } else {
                throw new InvokeException('Unable to invoke non-object instance.');
            }

            // invokeArgs expects a object or null
            // $this->call does not support calling static methods since we always resolve the class reference.
            if (is_object($classInstance) === false) {
                throw new InvokeException('Unable to resolve class instance');
            }

            return $method->invokeArgs($classInstance, $this->resolveArguments($method, $parameters));
        }

        if (is_callable($callable) || (is_string($callable) && function_exists($callable))) {
            $function = new \ReflectionFunction($callable);
            return $function->invokeArgs($this->resolveArguments($function, $parameters));
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
            $this->definitionSource->set($id, $value);
        } else {
            $this->setSingleton($id, $value);
        }
    }

    /**
     * Build a class without singleton cache.
     *
     * @see get()
     *
     * @param string $id
     * @param array<string, mixed> $parameters
     *
     * @return mixed
     *
     * @throws NotFoundExceptionInterface
     * @throws UnresolvedContainerException
     */
    public function make(string $id, array $parameters = [])
    {
        $definition = $this->definitionSource->get($id);

        if (isset($this->entriesBeingResolved[$id])) {
            throw new CircularDependencyException('Circular dependency detected while resolving entry ' . $id);
        }
        $this->entriesBeingResolved[$id] = true;

        try {
            if ($definition instanceof AbstractDefinition) {
                return $this->resolveDefinition($definition, $id);
            }

            return $this->resolveClass($id, $parameters);
        } finally {
            unset($this->entriesBeingResolved[$id]);
        }
    }

    /**
     * @param string $id
     * @param object|mixed $value
     */
    protected function setSingleton(string $id, $value): void
    {
        if (is_object($value) === true) {
            $definition = $this->definitionSource->get($id);
            $reference = new WeakReference($value);

            if ($definition !== null && $definition->isSingletonLifetime() === true) {
                $reference = new SingletonReference($value);
            }

            $this->singletons[$id] = $reference;
            return;
        }

        $this->singletons[$id] = $value;
    }

    /**
     * @param AbstractDefinition|mixed $value
     * @param string $referencedEntryName
     * @return mixed
     * @throws \Exception
     */
    private function resolveValue($value, $referencedEntryName)
    {
        if ($value instanceof AbstractDefinition) {
            return $this->resolveDefinition($value, $referencedEntryName);
        }

        return $value;
    }

    /**
     * @param AbstractDefinition $definition
     * @param string $requestedId
     * @return mixed
     * @throws NotFoundException
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    private function resolveDefinition(AbstractDefinition $definition, $requestedId)
    {
        if ($definition instanceof DefinitionValue) {
            return $this->resolveValue($definition->getValue(), $requestedId);
        }

        if ($definition instanceof DefinitionReference) {
            return $this->get($definition->getEntryName());
        }

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

        // @codeCoverageIgnoreStart
        throw new \Exception('Unsupported definition');
        // @codeCoverageIgnoreEnd
    }

    /**
     * Construct a class instance and resolve all parameters not provided in the parameters array.
     *
     * @param string $className
     * @param array<string, mixed> $parameters
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
     * @param array<string, mixed> $arguments
     *
     * @return array<string, mixed>
     *
     * @throws UnresolvedContainerException
     */
    public function resolveArguments(\ReflectionFunctionAbstract $function, array $arguments = []): array
    {
        $resolvedParameters = [];
        $parameters = $this->argumentResolver->resolve($function, $arguments);

        foreach ($parameters as $parameter) {
            /** @var Argument $parameter */

            $previousException = null;

            // Case #1: Did someone provide a value?
            if ($parameter->isResolved()) {
                $resolvedParameters[$parameter->getName()] = $this->resolveValue($parameter->getValue(), $parameter->getName());
                continue;
            }

            // Case #2: Definition entry ID as typehint?
            $typeHint = $parameter->getTypeHintClassName();
            if ($typeHint !== null) {
                try {
                    $resolvedParameters[$parameter->getName()] = $this->get($typeHint);
                    continue;
                } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
                    $previousException = $e; // Don't report now, we might have an optional value to use.
                }
            }

            // Case #3: Optional?
            if ($parameter->isOptional()) {
                $resolvedParameters[$parameter->getName()] = $parameter->getDefaultValue();
                continue;
            }

            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on class ' . ($parameter->getDeclaringClassName() ?? 'N/A'), 0, $previousException);
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
    public function has(string $id): bool
    {
        return $this->definitionSource->has($id);
    }
}
