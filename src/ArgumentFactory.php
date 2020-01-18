<?php

declare(strict_types=1);

namespace HbLib\Container;

class ArgumentFactory
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

    public function __construct()
    {
        $this->name = '';
        $this->isOptional = false;
        $this->isResolved = false;
    }

    public function make(): Argument
    {
        $argument = new Argument($this->name, $this->typeHintClassName, $this->isOptional, $this->defaultValue);
        $argument->setValue($this->value);
        $argument->setIsResolved($this->isResolved);
        $argument->setDeclaringClassName($this->declaringClassName);

        return $argument;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }

    public function setTypeHintClassName(?string $typeHintClassName): void
    {
        $this->typeHintClassName = $typeHintClassName;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value): void
    {
        $this->value = $value;
    }

    public function setIsResolved(bool $isResolved): void
    {
        $this->isResolved = $isResolved;
    }

    public function setIsOptional(bool $isOptional): void
    {
        $this->isOptional = $isOptional;
    }

    /**
     * @param mixed $defaultValue
     */
    public function setDefaultValue($defaultValue): void
    {
        $this->defaultValue = $defaultValue;
    }

    public function setDeclaringClassName(?string $declaringClassName): void
    {
        $this->declaringClassName = $declaringClassName;
    }
}
