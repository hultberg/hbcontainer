<?php

declare(strict_types=1);

namespace HbLib\Container;

use function is_object;

abstract class CompiledContainer extends Container
{
    /**
     * @var array
     */
    protected array $methodMapping;

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
        if (isset($this->singletons[$id]) === false) {
            $method = $this->methodMapping[$id] ?? null;

            if ($method !== null) {
                $value = $this->$method();
                $this->setSingleton($id, $value);

                return $value;
            }
        }

        return parent::get($id);
    }

    /**
     * @param string $entryName
     * @param array<string, mixed> $parameters
     * @return mixed
     * @throws InvokeException
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    protected function resolveFactory(string $entryName, array $parameters = [])
    {
        $definition = $this->definitionSource->get($entryName);

        if ($definition instanceof DefinitionFactory) {
            return $this->call($definition->getClosure(), $parameters);
        }

        // Unless someone changed a definition in runtime... this will happen.
        throw new \RuntimeException('Definition ' . $entryName . ' is not a factory');
    }
}
