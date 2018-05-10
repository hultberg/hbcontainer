<?php declare(strict_types=1);

namespace HbLib\Container {
    
    function factory(callable $callable) {
        $class = new \stdClass();
        $class->type = 'factory';
        $class->callable = new \ReflectionFunction($callable);
        
        return $class;
    }
        
    function getClass(string $class) {
        return factory(function(\Psr\Container\ContainerInterface $container) use ($class) {
            return $container->get($class);
        });
    }
    
}