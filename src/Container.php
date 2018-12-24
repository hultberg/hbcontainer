<?php declare(strict_types=1);

namespace HbLib\Container;

use function count;
use Ds\Collection;
use Ds\Map;
use Ds\Set;
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
     * @var Map
     */
    protected $singletons;

    /**
     * @var DefinitionSource
     */
    protected $definitionSource;

    /**
     * @var Set
     */
    protected $entriesBeingResolved;

    /**
     * @var ArgumentResolverInterface
     */
    private $argumentResolver;

    public function __construct(DefinitionSource $definitionSource = null, ArgumentResolverInterface $argumentResolver = null)
    {
        $this->singletons = new Map();
        $this->entriesBeingResolved = new Set();

        $this->definitionSource = $definitionSource ?? new DefinitionSource();
        $this->definitionSource->set(ContainerInterface::class, new DefinitionReference(self::class));
        $this->definitionSource->set(FactoryInterface::class, new DefinitionReference(self::class));
        $this->definitionSource->set(InvokerInterface::class, new DefinitionReference(self::class));

        $this->argumentResolver = $argumentResolver ?? new ArgumentResolver();

        $argumentResolverClassName = get_class($this->argumentResolver);
        $this->definitionSource->set($argumentResolverClassName, new DefinitionValue($this->argumentResolver));
        $this->definitionSource->set(ArgumentResolverInterface::class, new DefinitionReference($argumentResolverClassName));

        // Register the container itself.
        $this->singletons->put(self::class, $this);
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
        if (!$this->singletons->hasKey($id)) {
            // Attempt to build the $id class
            $this->singletons->put($id, $this->make($id));
        }

        return $this->singletons->get($id);
    }

    /**
     * Call the given function using the given parameters.
     *
     * @param callable|array|string|\Closure $callable Function to call.
     * @param null|Map|array $parameters Parameters to use.
     *
     * @return mixed Result of the function.
     *
     * @throws InvokeException Base exception class for all the sub-exceptions below.
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    public function call($callable, $parameters = null)
    {
        if (is_array($parameters)) $parameters = new Map($parameters);
        else if (!($parameters instanceof Map)) $parameters = null;

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

            return $method->invokeArgs($classInstance, $this->resolveArguments($method, $parameters)->toArray());
        }

        if (is_callable($callable) || (is_string($callable) && function_exists($callable))) {
            $function = new \ReflectionFunction($callable);
            return $function->invokeArgs($this->resolveArguments($function, $parameters)->toArray());
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
            $this->singletons->put($id, $value);
        }
    }

    /**
     * Build a class without cache.
     *
     * @see get()
     *
     * @param string $id
     * @param array|Map|null $parameters
     *
     * @return mixed
     *
     * @throws NotFoundExceptionInterface
     * @throws UnresolvedContainerException
     */
    public function make(string $id, $parameters = null)
    {
        if (is_array($parameters)) $parameters = new Map($parameters);
        else if (!($parameters instanceof Map)) $parameters = null;

        $definition = $this->definitionSource->get($id);

        if ($this->entriesBeingResolved->contains($id)) {
            throw new CircularDependencyException('Circular dependency detected while resolving entry ' . $id);
        }
        $this->entriesBeingResolved->add($id);

        try {
            if ($definition instanceof AbstractDefinition) {
                return $this->resolveDefinition($definition, $id);
            }

            if (is_callable($definition)) {
                return $this->call($definition);
            }

            if ($definition !== null) {
                // Return whatever...
                return $definition;
            }

            return $this->resolveClass($id, $parameters);
        } finally {
            $this->entriesBeingResolved->remove($id);
        }
    }

    private function resolveValue($value, $referencedEntryName)
    {
        if ($value instanceof AbstractDefinition) {
            return $this->resolveDefinition($value, $referencedEntryName);
        }

        return $value;
    }

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
            return $function->invokeArgs($this->resolveArguments($function, $definition->getParameters())->toArray());
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
     * @param null|Map  $parameters
     * @return mixed
     */
    private function resolveClass(string $className, Map $parameters = null)
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

        return $reflection->newInstanceArgs($this->resolveArguments($constructor, $parameters)->toArray());
    }

    /**
     * Resolve parameters of a reflection function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param null|Map $arguments
     *
     * @return Collection
     *
     * @throws UnresolvedContainerException
     */
    public function resolveArguments(\ReflectionFunctionAbstract $function, Map $arguments = null): Collection
    {
        $resolvedParameters = new Map();
        $parameters = $this->argumentResolver->resolve($function, $arguments);

        foreach ($parameters as $parameter) {
            /** @var Argument $parameter */

            $previousException = null;

            // Case #1: Did someone provide a value?
            if ($parameter->isResolved()) {
                $resolvedParameters->put($parameter->getName(), $this->resolveValue($parameter->getValue(), $parameter->getName()));
                continue;
            }

            // Case #2: Definition entry ID as typehint?
            $typeHint = $parameter->getTypeHintClassName();
            if ($typeHint !== null) {
                try {
                    $resolvedParameters->put($parameter->getName(), $this->get($typeHint));
                    continue;
                } catch (NotFoundExceptionInterface|ContainerExceptionInterface $e) {
                    $previousException = $e; // Don't report now, we might have an optional value to use.
                }
            }

            // Case #3: Optional?
            if ($parameter->isOptional()) {
                $resolvedParameters->put($parameter->getName(), $parameter->getDefaultValue());
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
    public function has($id)
    {
        return $this->definitionSource->has($id);
    }
}
