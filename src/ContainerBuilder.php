<?php

declare(strict_types=1);

namespace HbLib\Container;

class ContainerBuilder
{
    /**
     * @var DefinitionSource
     */
    private $definitions;

    /**
     * @var string
     */
    private $containerClass;

    /**
     * @var bool
     */
    private $enableCompiling;

    /**
     * @var string
     */
    private $compileFilePath;

    /**
     * @param array|DefinitionSource $definitions
     */
    public function __construct($definitions)
    {
        $this->containerClass = Container::class;

        $this->enableCompiling = false;
        $this->compileFilePath = '';

        // TODO: Handle when someone does not pass an array or DefinitionSource. It is document thou...
        $this->definitions = $definitions instanceof DefinitionSource ? $definitions : new DefinitionSource($definitions);
    }

    public function enableCompiling(string $filePath = null, string $className = null): void
    {
        $this->enableCompiling = true;

        // A nice default below, put it in the configured temp folder.
        $this->compileFilePath = $filePath ?? sys_get_temp_dir() . '/CompiledContainer.php';

        // The default CompiledContainer class. It is in the global namespace.
        $this->containerClass = $className ?? '\CompiledContainer';
    }

    public function build(): Container
    {
        // Put the class into a variable so we can create it dynamically later.
        // (php does not support doing `new $this->containerClass`)
        $containerClass = $this->containerClass;

        // Compiling is enabled, lets go.
        if ($this->enableCompiling) {
            $compiler = new Compiler($this->compileFilePath, $containerClass);
            $compiledClassFile = $compiler->compile($this->definitions);

            // In case our generated container class is not autoloaded, load it.
            // The container might be created multiple times in the runtime.
            if (!class_exists($containerClass, false)) {
                require $compiledClassFile;
            }
        }

        return new $containerClass($this->definitions);
    }
}
