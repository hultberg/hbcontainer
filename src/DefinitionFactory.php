<?php declare(strict_types=1);

namespace HbLib\Container;

use Closure;
use Ds\Map;
use mysql_xdevapi\Collection;

class DefinitionFactory extends AbstractDefinition
{
    /**
     * @var Closure
     */
    private $closure;

    /**
     * @var Map
     */
    private $parameters;

    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
        $this->parameters = new Map();
    }

    public function getClosure(): Closure
    {
        return $this->closure;
    }

    public function getParameters(): Map
    {
        return $this->parameters;
    }

    public function parameter(string $name, $value)
    {
        $this->parameters->put($name, $value);

        return $this;
    }
}
