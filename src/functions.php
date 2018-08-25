<?php declare(strict_types=1);

namespace HbLib\Container {

    function factory($callable): DefinitionFactory
    {
        return DefinitionFactory::fromCallable($callable);
    }

    function resolve(string $key = null): DefinitionClass
    {
        return new DefinitionClass($key);
    }

    function reference(string $key): DefinitionReference
    {
        return new DefinitionReference($key);
    }
    
    function value($value): DefinitionValue
    {
        return new DefinitionValue($value);
    }

}
