<?php

declare(strict_types=1);

namespace HbLib\Container;

class Argument
{
    /**
     * @var string
     */
    private $name;

    /**
     * @var string|null
     */
    private $typeHintClassName;

    /**
     * @var mixed
     */
    private $value;

    /**
     * If this argument was provided in ArgumentResolverInterface::resolve second argument.
     *
     * @var bool
     */
    private $isResolved;

    /**
     * @var bool
     */
    private $isOptional;

    /**
     * @var mixed
     */
    private $defaultValue;

    /**
     * @var string|null
     */
    private $declaringClassName;

    /**
     * @param string $name
     * @param string|null $typeHintClassName
     * @param bool   $isOptional
     * @param mixed  $defaultValue
     */
    public function __construct(
        string $name,
        string|null $typeHintClassName = null,
        bool $isOptional = false,
        $defaultValue = null
    ) {
        $this->name = $name;
        $this->typeHintClassName = $typeHintClassName;
        $this->isOptional = $isOptional;
        $this->defaultValue = $defaultValue;
        $this->isResolved = false;
        $this->declaringClassName = null;
    }

    /**
     * @return string|null
     */
    public function getDeclaringClassName(): ?string
    {
        return $this->declaringClassName;
    }

    /**
     * @param string|null $declaringClassName
     */
    public function setDeclaringClassName(?string $declaringClassName): void
    {
        $this->declaringClassName = $declaringClassName;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }

    public function isResolved(): bool
    {
        return $this->isResolved;
    }

    public function setIsResolved(bool $isResolved): void
    {
        $this->isResolved = $isResolved;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    /**
     * @return string
     */
    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return string|null
     */
    public function getTypeHintClassName(): ?string
    {
        return $this->typeHintClassName;
    }

    /**
     * @return bool
     */
    public function isOptional(): bool
    {
        return $this->isOptional;
    }

    /**
     * @return mixed
     */
    public function getDefaultValue()
    {
        return $this->defaultValue;
    }
}
