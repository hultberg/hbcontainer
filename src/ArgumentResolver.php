<?php declare(strict_types=1);

namespace HbLib\Container;

class ArgumentResolver implements ArgumentResolverInterface
{
    /**
     * @var DefinitionSource
     */
    private $definitionSource;
    
    /**
     * @var array
     */
    private $resolved;
    
    /**
     * @param DefinitionSource $definitionSource
     */
    public function __construct(DefinitionSource $definitionSource)
    {
        $this->definitionSource = $definitionSource;
        $this->resolved = [];
    }
    
    /**
     * Resolve parameters of a reflection function.
     *
     * @param \ReflectionFunctionAbstract $function
     * @param array $arguments
     *
     * @return Argument[]
     *
     * @throws UnresolvedContainerException
     */
    public function resolve(\ReflectionFunctionAbstract $function, array $arguments = array()): array
    {
        $resolvedArguments = array();

        foreach ($function->getParameters() as $parameter) {
            // Identify if the type is a class we can attempt to build.
            $type = $parameter->getType();
            $parameterName = $parameter->getName();

            if (array_key_exists($parameterName, $arguments)) {
                $argument = new Argument($parameterName, null);
                $argument->setIsResolved(true);
                $argument->setValue($arguments[$parameterName]);
                $resolvedArguments[$parameterName] = $argument;
                continue;
            } else {
                $isOptional = $parameter->isOptional();
                $isDefaultValueAvailable = $parameter->isDefaultValueAvailable();
                
                // We must resolve this parameter.
                // Case #1: A class/interface/trait we can build
                if ($type !== null && !$type->isBuiltin()) {
                    $typeName = $type->getName();
                    $reflection = new \ReflectionClass($typeName);
                    
                    // Case #1.1: Is the .. thing(?) instantiable?
                    if ($reflection->isInstantiable()) {
                        $resolvedArguments[$parameterName] = new Argument($parameterName, $typeName, $isOptional, $isDefaultValueAvailable ? $parameter->getDefaultValue() : null);
                        continue;
                    }
                    
                    // Case #1.2: Is it an interface or abstract?
                    if ($reflection->isInterface() || $reflection->isAbstract()) {
                        // At this point, if there is an definition to the thing, its good.
                        if ($this->definitionSource->hasDefinition($typeName)) {
                            $resolvedArguments[$parameterName] = new Argument($parameterName, $typeName, $isOptional, $isDefaultValueAvailable ? $parameter->getDefaultValue() : null);
                            continue;
                        }
                        
                        // Not defined as a definition, lets hope it has a default value we can use.
                    }
                }

                // Case #2: Argument is optional and has a default value
                if ($isOptional && $isDefaultValueAvailable) {
                    // Some builtin with a default value.
                    $resolvedArguments[$parameterName] = new Argument($parameterName, null, true, $isDefaultValueAvailable ? $parameter->getDefaultValue() : null);
                    continue;
                }
            }

            $declaringClass = $parameter->getDeclaringClass();
            throw new UnresolvedContainerException('Unable to resolve parameter ' . $parameter->getName() . ' on entity ' . ($declaringClass ? $declaringClass->getName() : 'N/A'));
        }

        return $resolvedArguments;
    }
}
