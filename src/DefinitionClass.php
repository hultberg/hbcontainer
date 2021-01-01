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
     * DefinitionClass constructor.
     * @phpstan-param class-string|null $className
     * @param string|null $className
     * @param array<string, mixed> $parameters
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

    /**
     * @return array<string, mixed>
     */
    public function getParameters(): array
    {
        return $this->parameters;
    }

    public function parameter(string $key, mixed $value): static
    {
        $this->parameters[$key] = $value;
        return $this;
    }
}
