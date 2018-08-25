<?php declare(strict_types=1);

namespace HbLib\Container;

class DefinitionReference extends AbstractDefinition
{
    /**
     * @var string
     */
    private $entryName;

    /**
     * @inheritDoc
     */
    public function __construct($entryName)
    {
        $this->entryName = $entryName;
    }

    /**
     * @return string
     */
    public function getEntryName(): string
    {
        return $this->entryName;
    }
}
