<?php declare(strict_types=1);

namespace HbLib\Container;

class DefinitionReference extends AbstractDefinition
{
    /**
     * @var string
     */
    private $className;

    /**
     * @inheritDoc
     */
    public function __construct($className)
    {
        $this->className = $className;
    }

    /**
     * @return string
     */
    public function getClassName(): string
    {
        return $this->className;
    }
}
