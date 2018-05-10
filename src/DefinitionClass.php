<?php declare(strict_types=1);

namespace HbLib\Container;

class DefinitionClass extends AbstractDefinition
{
    /**
     * @var string|null
     */
    private $className = null;

    /**
     * @var array
     */
    private $parameters = [];

    /**
     * @inheritDoc
     */
    public function __construct($className = null, array $parameters = [])
    {
        $this->className = $className;
        $this->parameters = $parameters;
    }

    /**
     * @return string|null
     */
    public function getClassName(): ?string
    {
        return $this->className;
    }

    /**
     * @return array
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function parameter(string $key, $value)
    {
        $this->parameters[$key] = $value;
        return $this;
    }
}
