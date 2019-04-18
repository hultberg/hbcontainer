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

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
        $this->parameters = [];
    }

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
}
