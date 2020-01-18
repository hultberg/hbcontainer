<?php declare(strict_types=1);

namespace HbLib\Container;

use Closure;

class DefinitionFactory extends AbstractDefinition
{
    /**
     * @var Closure
     */
    private Closure $closure;

    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    public function __construct(Closure $closure)
    {
        parent::__construct();

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

    /**
     * @param string $name
     * @param mixed $value
     * @return $this
     */
    public function parameter(string $name, $value)
    {
        $this->parameters[$name] = $value;

        return $this;
    }
}
