<?php

declare(strict_types=1);

namespace HbLib\Container;

use function count;
use function is_iterable;

class Compiler
{
    private ?DefinitionSource $definitions;
    private string $compiledClassName;
    private string $compiledParentClassName;
    private ArgumentResolverInterface $argumentResolver;
    private \SplFileInfo $fileInfo;

    /**
     * @var CompiledEntry[]
     */
    private array $definitionsToCompile;

    /**
     * @var CompiledMethod[]
     */
    private array $methods;

    /**
     * @var array<string, string>
     */
    private array $entryToMethods;

    public function __construct(string $filePath, string $compiledClassName = 'CompiledContainer')
    {
        $this->definitions = null;
        $this->argumentResolver = new ArgumentResolver();
        $this->fileInfo = new \SplFileInfo($filePath);
        $this->compiledClassName = $compiledClassName;
        $this->compiledParentClassName = '\\' . CompiledContainer::class;
        $this->methods = [];
        $this->entryToMethods = [];
    }

    public function compile(DefinitionSource $definitions): string
    {
        // Compile the container if we have not already done it.
        if (!$this->fileInfo->isFile()) {
            $this->definitions = $definitions;
            $this->definitionsToCompile = [];

            foreach ($this->definitions as $id => $value) {
                $this->definitionsToCompile[] = new CompiledEntry($id, $value);
            }

            foreach ($this->definitionsToCompile as $entry) {
                /** @var CompiledEntry $entry */

                $definition = $entry->getValue();
                $id = $entry->getId();

                // We might have added an definition as it it did not exist.
                if ($definition === null) {
                    $definition = $definitions->get($id);
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

            $this->entryToMethods = [];
            $this->methods = [];
            $this->definitions = null;
        }

        return $this->fileInfo->getPathname();
    }

    private function compileDefinition(string $entryName, AbstractDefinition $definition): string
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
                if (!isset($this->entryToMethods[$entryName])) {
                    $this->definitionsToCompile[] = new CompiledEntry($entryName);
                }

                $code = 'return $this->get(' . $this->compileValue($entryName) . ');';
                break;

            case $definition instanceof DefinitionFactory:
                // Reference to another definition.
                $resolvedParameters = $this->resolveParameters(new \ReflectionFunction($definition->getClosure()), $definition->getParameters());

                $parametersString = '';
                if (count($resolvedParameters) > 0) {
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
        }

        $this->methods[] = new CompiledMethod($methodName, $code);
        return $methodName;
    }

    /**
     * @param \ReflectionFunctionAbstract $function
     * @param array<string, mixed> $extraParameters
     * @return array<string, mixed>
     * @throws UnresolvedContainerException
     * @throws \ReflectionException
     */
    private function resolveParameters(\ReflectionFunctionAbstract $function, array $extraParameters = []): array
    {
        $parameters = $this->argumentResolver->resolve($function, $extraParameters);
        $resolvedParameters = [];

        foreach ($parameters as $parameter) {
            /** @var Argument $parameter */

            if ($parameter->isResolved()) {
                $resolvedParameters[$parameter->getName()] = $parameter->getValue();
                continue;
            }

            // Case #2: Definition entry ID as typehint?
            $typeHint = $parameter->getTypeHintClassName();
            if ($typeHint !== null) {
                $class = new \ReflectionClass($typeHint);

                if ($class->isInstantiable() || $this->definitions?->has($typeHint)) {
                    $resolvedParameters[$parameter->getName()] = new DefinitionReference($typeHint);
                    continue;
                }
            }

            // Case #3: Optional?
            if ($parameter->isOptional()) {
                $resolvedParameters[$parameter->getName()] = $parameter->getDefaultValue();
                continue;
            }

            // Something is wrong... might be an interface that has no definition entry.
            // It might be set in runtime so just let the container compile it.
            $resolvedParameters[$parameter->getName()] = new DefinitionReference((string) $typeHint);
        }

        return $resolvedParameters;
    }

    /**
     * @param AbstractDefinition|iterable|mixed $value
     * @return string
     */
    private function compileValue($value): string
    {
        if ($value instanceof AbstractDefinition) {
            $entryName = uniqid('SubEntry', true);
            $methodName = $this->compileDefinition($entryName, $value);

            return '$this->' . $methodName . '()';
        }

        if (is_iterable($value)) {
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
