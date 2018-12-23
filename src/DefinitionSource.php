<?php

declare(strict_types=1);

namespace HbLib\Container;

use Ds\Map;
use OutOfBoundsException;

class DefinitionSource
{
    /**
     * @var Map
     */
    private $definitions;

    /**
     * @param array $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = new Map($definitions);
    }

    /**
     * @return Map
     */
    public function getDefinitions(): Map
    {
        return $this->definitions;
    }

    public function hasDefinition($id): bool
    {
        return $this->definitions->hasKey($id);
    }

    public function getDefinition($id)
    {
        return $this->definitions->get($id, null);
    }

    public function setDefinition($id, $value): void
    {
        $this->definitions->put($id, $value);
    }
}
