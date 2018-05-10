<?php declare(strict_types=1);

namespace HbLib\Container;

use ReflectionFunction;

class DefinitionFactory extends AbstractDefinition
{
    /**
     * @var ReflectionFunction
     */
    private $function;
    
    /**
     * @param ReflectionFunction $function
     */
    public function __construct(ReflectionFunction $function)
    {
        $this->function = $function;
    }
    
    public static function fromCallable(callable $callable)
    {
        return new self(new \ReflectionFunction($callable));
    }
    
    /**
     * @return ReflectionFunction
     */
    public function getFunction(): ReflectionFunction
    {
        return $this->function;
    }
}