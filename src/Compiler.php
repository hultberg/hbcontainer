<?php

declare(strict_types=1);        

namespace HbLib\Container;

class Compiler
{
    /**
     * @var \ArrayIterator
     */
    private $definitionsToCompile;
    
    /**
     * @var string
     */
    private $compiledClassName;
    
    /**
     * @var string
     */
    private $compiledParentClassName;
        
    /**
     * @var array
     */
    private $methods;
    
    /**
     * @var array
     */
    private $entryToMethods;
    
    /**
     * @var \SplFileInfo
     */
    private $fileInfo;
    
    public function __construct(string $filePath, string $compiledClassName = 'CompiledContainer')
    {
        $this->fileInfo = new \SplFileInfo($filePath);
        $this->compiledClassName = $compiledClassName;
        $this->compiledParentClassName = '\\' . CompiledContainer::class;
        $this->methods = [];
        $this->entryToMethods = [];
    }
    
    public function compile(DefinitionSource $definitions)
    {
        // Compile the container if we have not already done it.
        if (!$this->fileInfo->isFile()) {
            $this->definitionsToCompile = new \ArrayIterator($definitions->getDefinitions());
            
            foreach ($this->definitionsToCompile as $id => $definition) {
                // We might have added an definition as it it did not exist.
                if ($definition === null) {
                    $definition = $definitions->getDefinition($id);
                }
                
                // Compile a definition.
                if ($definition instanceof AbstractDefinition) {
                    $this->compileDefinition($id, $definition);
                }
                
                // All other values in the container is left alone is resolved via
                // the base container class instance.
            }
            
            // Render the template.
            ob_start();
            require __DIR__ . '/compiled_template.php';
            $fileContent = ob_get_clean();
            
            // Prepend the php start tag... so php can parse it later since its a class.
            $fileContent = '<?php' . PHP_EOL . $fileContent;
            
            $fileObj = $this->fileInfo->openFile('w');
            $fileObj->fwrite($fileContent);
            unset($fileObj); // Release the file pointer.
            
            // chmod the created compiled container adding the umask of the current runtime.
            // example: (umask=002) is 436 (664 in oct)
            // example: (umask=0022) is 420 (644 in oct)
            // TODO: unsilence the chmod and report the error.
            @chmod($this->fileInfo->getPathname(), 0666 & ~umask());
        }
        
        return $this->fileInfo->getPathname();
    }
    
    private function compileDefinition($entryName, AbstractDefinition $definition)
    {
        // Already compiled the entry?
        if (isset($this->entryToMethods[$entryName])) {
            return $this->entryToMethods[$entryName];
        }
    
        $methodName = str_replace('.', '', uniqid('get', true));
        $this->entryToMethods[$entryName] = $methodName;
        
        if ($definition instanceof DefinitionReference) {
            // Reference to another definition.
            $className = $definition->getClassName();
            
            // Ensure we compile this definition too.
            if (!isset($this->definitionsToCompile[$className])) {
                $this->definitionsToCompile[$className] = null;
            }
            
            $code = 'return $this->get(' . $this->compileValue($className) . ');';
            $this->methods[$methodName] = $code;
        } else if ($definition instanceof DefinitionFactory) {
            // Reference to another definition.
        
            $factory            = $definition->getClosure();
            $reflectionFunction = new \ReflectionFunction($factory);
            $resolvedParameters = $this->resolveParameters($reflectionFunction->getParameters(), $definition->getParameters());
        
            $parametersString = '';
            if (!empty($resolvedParameters)) {
                $parametersString = ', ' . $this->compileValue($resolvedParameters);
            }
            
            $code = 'return $this->resolveFactory(' . $this->compileValue($entryName) . '' . $parametersString . ');';
        
            $this->methods[$methodName] = $code;
        } else if ($definition instanceof DefinitionClass) {
            $className = $definition->getClassName() ?? $entryName;
            
            $reflectionClass = new \ReflectionClass($className);
            $constructor = $reflectionClass->getConstructor();
            $definedParameters = $definition->getParameters();
            
            $parametersString = '';
            if ($constructor !== null) {
                $resolvedParameters = array_values($this->resolveParameters($constructor->getParameters(), $definedParameters));
                $compiledResolvedParameters = array_map([$this, 'compileValue'], $resolvedParameters);
                $parametersString = implode(',', $compiledResolvedParameters);
            }
            
            $code = 'return new ' . $className . '(' . $parametersString . ');';
            $this->methods[$methodName] = $code;
        }
        
        return $methodName;
    }
    
    private function resolveParameters(array $parameters, array $extraParameters = [])
    {
        $resolvedParameters = [];
        
        foreach ($parameters as $parameter) {
            $name = $parameter->getName();
            
            if (array_key_exists($parameter->getName(), $extraParameters)) {
                $extraParametersValue = $extraParameters[$parameter->getName()];
                
                $resolvedParameters[$name] = $extraParametersValue;
                continue;
            }
            
            if (!$parameter->isOptional()) {
                $type = $parameter->getType();
                
                if ($type !== null && !$type->isBuiltin() && class_exists($type->getName())) {
                    // a class we can, create a reference to it and compile.
                    $resolvedParameters[$name] = new DefinitionReference($type->getName());
                    continue;
                }
            }
            
            throw new \RuntimeException('Failed to compile a single parameter');
        }
        
        return $resolvedParameters;
    }
    
    private function compileValue($value)
    {
        if ($value instanceof AbstractDefinition) {
            $entryName = uniqid('SubEntry');
            $methodName = $this->compileDefinition($entryName, $value);
            
            return '$this->' . $methodName . '()';
        }
        
        if (is_array($value)) {
            $valueAsString = '';
            
            foreach ($value as $key => $item) {
                $compiledValue = $this->compileValue($item);
                $key = var_export($key, true);
                
                $valueAsString .= "$key => $compiledValue," . PHP_EOL;
            }
            
            return "[$valueAsString]";
        }
        
        return var_export($value, true);
    }
}