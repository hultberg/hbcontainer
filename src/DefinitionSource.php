<?php

declare(strict_types=1);        

namespace HbLib\Container;

class DefinitionSource
{
    /**
     * @var array
     */
    private $definitions;
    
    /**
     * @param array $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions;
    }
    
    /**
     * @return array
     */
    public function getDefinitions(): array
    {
        return $this->definitions;
    }
    
    public function hasDefinition($id): bool
    {
        return array_key_exists($id, $this->definitions);
    }
    
    public function getDefinition($id)
    {
        return $this->definitions[$id] ?? null;
    }
    
    public function setDefinition($id, $value): void
    {
        $this->definitions[$id] = $value;
    }
}