<?php declare(strict_types=1);

namespace HbLib\Container {
    
    function factory(callable $callable): DefinitionFactory
    {
        return DefinitionFactory::fromCallable($callable);
    }
        
    function get(string $key): DefinitionClass
    {
        return new DefinitionClass($key);
    }
    
}