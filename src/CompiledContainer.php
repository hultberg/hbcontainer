<?php

declare(strict_types=1);

namespace HbLib\Container;

abstract class CompiledContainer extends Container
{
    /**
     * @var array
     */
    protected $methodMapping;

    public function __construct(DefinitionSource $definitionSource = null, ArgumentResolverInterface $argumentResolver = null)
    {
        parent::__construct($definitionSource, $argumentResolver);

//        $this->methodMapping = new Map();
        $this->_initialize();
    }

    protected function _initialize(): void
    {
        // no-op
    }

    /**
     * @inheritDoc
     */
    public function get($id)
    {
        // Have we resolved the ID before?
        if (isset($this->singletons[$id])) {
            return $this->singletons[$id];
        }

        $method = $this->methodMapping[$id] ?? null;

        if ($method !== null) {
            $value = $this->$method();
            $this->singletons[$id] = $value;

            return $value;
        }

        return parent::get($id);
    }

    protected function resolveFactory($entryName, array $parameters = [])
    {
        $definition = $this->definitionSource->get($entryName);

        if ($definition instanceof DefinitionFactory) {
            return $this->call($definition->getClosure(), $parameters);
        }

        // Unless someone changed a definition in runtime... this will happen.
        throw new \RuntimeException('Definition ' . $entryName . ' is not a factory');
    }
}
