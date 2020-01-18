<?php declare(strict_types=1);

namespace HbLib\Container;

class DefinitionReference extends AbstractDefinition
{
    private string $entryName;

    /**
     * @inheritDoc
     */
    public function __construct(string $entryName)
    {
        parent::__construct();

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
