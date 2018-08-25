<?php declare(strict_types=1);

namespace HbLib\Container;

class DefinitionValue extends AbstractDefinition
{
    /**
     * @var mixed
     */
    private $value;

    public function __construct($value)
    {
        $this->value = $value;
    }

    public function getValue()
    {
        return $this->value;
    }
}