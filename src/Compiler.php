<?php

declare(strict_types=1);

namespace HbLib\Container;

use Laravel\SerializableClosure\Support\ReflectionClosure;

use function array_map;
use function count;
use function hash;
use function implode;
use function is_iterable;
use function mb_substr;
use function ob_get_clean;
use function ob_start;
use function random_bytes;
use function sha1;
use function str_replace;
use function strpos;
use function strrpos;
use function substr;
use function uniqid;
use function var_export;

class Compiler
{
    private string $compiledParentClassName;
    private ArgumentResolverInterface $argumentResolver;
    private DefinitionSource|null $definitions;

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

    public function __construct()
    {
        $this->argumentResolver = new ArgumentResolver();
        $this->compiledParentClassName = '\\' . CompiledContainer::class;
    }

    /**
     * @param DefinitionSource $definitions
     * @return string The compiled container class content
     */
    public function compile(DefinitionSource $definitions): string
    {
        // Compile the container if we have not already done it.
        $this->definitions = $definitions;
        $this->definitionsToCompile = [];

        $this->entryToMethods = [];
        $this->methods = [];

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

        $compiledClassName = '\HbCompiledContainer' . sha1(random_bytes(8));

        // Render the template.
        ob_start();
        require __DIR__ . '/compiled_template.php';
        $fileContent = ob_get_clean();

        // Prepend the php start tag... so php can parse it later since its a class.
        $fileContent = '<?php' . PHP_EOL . $fileContent;

        $this->definitions = null;

        return $fileContent;
    }

    private function compileDefinition(string $entryName, AbstractDefinition $definition): string
    {
        // Already compiled the entry?
        if (isset($this->entryToMethods[$entryName])) {
            return $this->entryToMethods[$entryName];
        }

        $returnType = 'mixed';
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

                $ref = $entryName;
                while ($ref instanceof DefinitionReference) {
                    $ref = $this->definitions?->get($ref->getEntryName());
                }

                if ($ref instanceof DefinitionClass) {
                    $returnType = $ref->getClassName() ?? $entryName;
                }

                $code = 'return $this->get(' . $this->compileValue($entryName) . ');';
                break;

            case $definition instanceof DefinitionFactory:
                // Reference to another definition.
                $closure = new ReflectionClosure($definition->getClosure());
                $resolvedParameters = $this->resolveParameters(new \ReflectionFunction($definition->getClosure()), $definition->getParameters());

                if (count($closure->getUseVariables()) > 0) {
                    // delegate to runtime since this closure uses context variables
                    $parametersString = '';
                    if (count($resolvedParameters) > 0) {
                        $parametersString = ', ' . $this->compileValue($resolvedParameters);
                    }

                    $code = 'return $this->resolveFactory(' . $this->compileValue($entryName) . '' . $parametersString . ');';
                    break;
                }

                $code = '';
                foreach ($resolvedParameters as $name => $resolvedParameter) {
                    $code .= '$' . $name . ' = ' . $this->compileValue($resolvedParameter) . ';' . PHP_EOL;
                }

                $closureCode = $closure->getCode();
                // I'm only interrested in the actual closure content not the full enclosure since
                // the closure get it's own method
                $closureCode = mb_substr($closureCode, strpos($closureCode, '{') + 1);
                $closureCode = mb_substr($closureCode, 0, strrpos($closureCode, '}') - 1);

                // insert closure code directly into the compiled container.
                $code .= $closureCode;

                if ($closure->getReturnType() instanceof \ReflectionNamedType) {
                    $returnType = $closure->getReturnType()->getName();
                }
                break;

            case $definition instanceof DefinitionClass:
                $className = $definition->getClassName() ?? $entryName;

                $reflectionClass = new \ReflectionClass($className);
                $constructor = $reflectionClass->getConstructor();
                $definedParameters = $definition->getParameters();

                $parametersString = '';
                if ($constructor !== null) {
                    $resolvedParameters = $this->resolveParameters($constructor, $definedParameters);
                    $parametersString = implode(', ', array_map(fn ($val) => $this->compileValue($val), $resolvedParameters));
                }

                $returnType = $className;
                $code = 'return new ' . $className . '(' . $parametersString . ');';
                break;

            case $definition instanceof DefinitionValue:
                $code = 'return ' . $this->compileValue($definition->getValue()) . ';';
                break;

            default:
                throw new \RuntimeException('Invalid definition');
        }

        $this->methods[] = new CompiledMethod($methodName, $code, $returnType);
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
