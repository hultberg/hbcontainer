<?php declare(strict_types=1);

namespace HbLib\Container {

    use Closure;
    use InvalidArgumentException;
    use function is_array;

    function factory(callable $callable): DefinitionFactory
    {
        if (is_array($callable)) $callable = \Closure::fromCallable($callable);
        else if (!($callable instanceof Closure)) throw new InvalidArgumentException('Invalid argument');

        return new DefinitionFactory($callable);
    }

    /**
     * @param class-string|null $key
     * @return DefinitionClass
     */
    function resolve(string $key = null): DefinitionClass
    {
        return new DefinitionClass($key);
    }

    function reference(string $key): DefinitionReference
    {
        return new DefinitionReference($key);
    }

    /**
     * @param mixed $value
     * @return DefinitionValue
     */
    function value($value): DefinitionValue
    {
        return new DefinitionValue($value);
    }

    function classNameExists(string $value): bool
    {
        return class_exists($value) || interface_exists($value);
    }

}
