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

            return \call_user_func_array($factory, $this->resolveParameters(new \ReflectionFunction($factory), $parameters));
        }

        $reflection = new \ReflectionClass($className);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return new $className(); // No constructor.
        }

        return $reflection->newInstanceArgs($this->resolveParameters($constructor, $parameters));
    }

    /**
     * @param \ReflectionFunctionAbstract $function
     * @param array $parameters
     *
     * @return array
     *
     * @throws UnresolvedContainerException
     */
    private function resolveParameters(\ReflectionFunctionAbstract $function, array $parameters = array()): array
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
                            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . $parameter->getDeclaringClass()->getName());
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
