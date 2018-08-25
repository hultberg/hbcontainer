<?php

declare(strict_types=1);        

namespace HbLib\Container;

abstract class CompiledContainer extends Container 
{
    /**
     * @inheritDoc
     */
    public function get($id)
    {
        // Have we resolved the ID before?
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }
        
        $method = static::METHOD_MAPPING[$id] ?? null;
        
        if ($method !== null) {
            $value = $this->$method();
            $this->singletons[$id] = $value;
            
            return $value;
        }
        
        return parent::get($id);
    }
    
    protected function resolveFactory($entryName, array $parameters = [])
    {
        $definition = $this->definitionSource->getDefinition($entryName);
        
        if ($definition instanceof DefinitionFactory) {
            return $this->call($definition->getClosure(), $parameters);
        }
        
        // Unless someone changed a definition in runtime... this will happen.
        throw new \RuntimeException('Definition ' . $entryName . ' is not a factory');
    }
}