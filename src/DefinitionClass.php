<?php declare(strict_types=1);

namespace HbLib\Container;

/**
 * An definition for a class that can have instances created.
 */
class DefinitionClass extends AbstractDefinition
{
    private ?string $className = null;

    /**
     * @var array<string, mixed>
     */
    private array $parameters;

    /**
     * @inheritDoc
     */
    public function __construct(?string $className = null, array $parameters = [])
    {
        parent::__construct();

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

    public function getParameters(): array
    {
        return $this->parameters;
    }

    /**
     * @param string $key
     * @param mixed $value
     * @return $this
     */
    public function parameter(string $key, $value)
    {
        $this->parameters[$key] = $value;
        return $this;
    }
}
