<?php

declare(strict_types=1);        

namespace HbLib\Container;

class Compiler
{
    /**
     * @var DefinitionSource
     */
    private $definitions;
    
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
     * @var ArgumentResolverInterface
     */
    private $argumentResolver;
    
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
            $this->definitions = $definitions;
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
            
            $this->entryToMethods = $this->methods = [];
            unset($this->definitions, $this->definitionsToCompile, $this->argumentResolver);
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
        
        switch (true) {
            case $definition instanceof DefinitionReference:
                // Reference to another definition.
                $entryName = $definition->getEntryName();
                
                // Ensure we compile this definition too.
                if (!isset($this->definitionsToCompile[$entryName])) {
                    $this->definitionsToCompile[$entryName] = null;
                }
                
                $code = 'return $this->get(' . $this->compileValue($entryName) . ');';
                break;
                
            case $definition instanceof DefinitionFactory:
                // Reference to another definition.
                $resolvedParameters = $this->resolveParameters(new \ReflectionFunction($definition->getClosure()), $definition->getParameters());
            
                $parametersString = '';
                if (!empty($resolvedParameters)) {
                    $parametersString = ', ' . $this->compileValue($resolvedParameters);
                }
                
                $code = 'return $this->resolveFactory(' . $this->compileValue($entryName) . '' . $parametersString . ');';
                break;
                
            case $definition instanceof DefinitionClass:
                $className = $definition->getClassName() ?? $entryName;
                
                $reflectionClass = new \ReflectionClass($className);
                $constructor = $reflectionClass->getConstructor();
                $definedParameters = $definition->getParameters();
                
                $parametersString = '';
                if ($constructor !== null) {
                    $resolvedParameters = $this->resolveParameters($constructor, $definedParameters);
                    
                    $i = 0;
                    foreach ($resolvedParameters as $parameter) {
                        $parametersString .= ($i++ > 0 ? ', ' : '') . $this->compileValue($parameter);
                    }
                }
                
                $code = 'return new ' . $className . '(' . $parametersString . ');';
                break;
                
            case $definition instanceof DefinitionValue:
                $code = 'return ' . $this->compileValue($definition->getValue()) . ';';
                break;
                
            default:
                throw new \RuntimeException('Invalid definition');
                break;
        }
        
        $this->methods[$methodName] = $code;
        return $methodName;
    }
    
    private function resolveParameters(\ReflectionFunctionAbstract $function, array $extraParameters = [])
    {
        if ($this->argumentResolver === null) {
            $this->argumentResolver = new ArgumentResolver();
        }
        
        $resolvedParameters = $this->argumentResolver->resolve($function, $extraParameters);
        
        if (!empty($resolvedParameters)) {
            reset($resolvedParameters);
            
            do {
                /** @var Argument $argument */
                $argument = current($resolvedParameters);
                
                if ($argument->isResolved()) {
                    $resolvedParameters[key($resolvedParameters)] = $argument->getValue();
                    continue;
                }
                
                // Case #2: Definition entry ID as typehint?
                $typeHint = $argument->getTypeHintClassName();
                if ($typeHint !== null) {
                    $class = new \ReflectionClass($typeHint);
                    
                    if ($class->isInstantiable() || $this->definitions->hasDefinition($typeHint)) {
                        $resolvedParameters[key($resolvedParameters)] = new DefinitionReference($typeHint);
                        continue;
                    }
                }
                
                // Case #3: Optional?
                if ($argument->isOptional()) {
                    $resolvedParameters[key($resolvedParameters)] = $argument->getDefaultValue();
                    continue;
                }
                
                // Something is wrong... might be an interface that has no definition entry.
                // It might be set in runtime so just let the container compile it.
                $resolvedParameters[key($resolvedParameters)] = new DefinitionReference($typeHint);
            } while (next($resolvedParameters));
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