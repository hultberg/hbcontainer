<?php declare(strict_types=1);

namespace HbLib\Container;

use Closure;

class DefinitionFactory extends AbstractDefinition
{
    /**
     * @var Closure
     */
    private $closure;
    
    /**
     * @var array
     */
    private $parameters;

    /**
     * @param Closure $closure
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
        $this->parameters = [];
    }

    public static function fromCallable($callable)
    {
        if (is_array($callable)) {
            $callable = function(InvokerInterface $invoker) use ($callable) {
                return $invoker->call($callable);
            };
        }

        return new self(Closure::fromCallable($callable));
    }

    /**
     * @return Closure
     */
    public function getClosure(): Closure
    {
        return $this->closure;
    }
    
    public function getParameters(): array
    {
        return $this->parameters;
    }
    
    public function parameter(string $name, $value)
    {
        $this->parameters[$name] = $value;
        
        return $this;
    }
    
    public function getTypeName(): string
    {
        return 'factory';
    }
}
