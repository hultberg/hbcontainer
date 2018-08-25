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
    
    /**
     * @param string $name
     */
    public function setName(string $name)
    {
        $this->name = $name;
    }

    /**
     * @param string|null $typeHintClassName
     */
    public function setTypeHintClassName(?string $typeHintClassName)
    {
        $this->typeHintClassName = $typeHintClassName;
    }

    /**
     * @param mixed $value
     */
    public function setValue($value)
    {
        $this->value = $value;
    }

    /**
     * @param bool $isResolved
     */
    public function setIsResolved(bool $isResolved)
    {
        $this->isResolved = $isResolved;
    }

    /**
     * @param bool $isOptional
     */
    public function setIsOptional(bool $isOptional)
    {
        $this->isOptional = $isOptional;
    }

    /**
     * @param mixed $defaultValue
     */
    public function setDefaultValue($defaultValue)
    {
        $this->defaultValue = $defaultValue;
    }

    /**
     * @param string|null $declaringClassName
     */
    public function setDeclaringClassName(?string $declaringClassName)
    {
        $this->declaringClassName = $declaringClassName;
    }
}