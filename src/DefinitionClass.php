<?php declare(strict_types=1);

namespace HbLib\Container;

use Ds\Map;

/**
 * An definition for a class that can have instances created.
 */
class DefinitionClass extends AbstractDefinition
{
    /**
     * @var string|null
     */
    private $className = null;

    /**
     * @var Map
     */
    private $parameters;

    /**
     * @inheritDoc
     */
    public function __construct($className = null, Map $parameters = null)
    {
        $this->className = $className;
        $this->parameters = $parameters ?? new Map();
    }

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    public function getParameters(): Map
    {
        return $this->parameters;
    }

    public function parameter(string $key, $value)
    {
        $this->parameters->put($key, $value);
        return $this;
    }
}
