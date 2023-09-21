<?php

declare(strict_types=1);

namespace HbLib\Container;

use function basename;
use function chmod;
use function clearstatcache;
use function dirname;
use function file_put_contents;
use function filter_var;
use function function_exists;
use function ini_get;
use function is_dir;
use function is_file;
use function is_writable;
use function opcache_invalidate;
use function rename;
use function sys_get_temp_dir;
use function tempnam;
use function umask;

class ContainerBuilder
{
    private DefinitionSource $definitions;
    private string|null $compiledContainerClassName;
    private bool $enableCompiling;
    private string|null $compileFilePath;

    /**
     * @param array<string, AbstractDefinition>|DefinitionSource $definitions
     */
    public function __construct($definitions)
    {
        $this->compiledContainerClassName = null;
        $this->enableCompiling = false;
        $this->compileFilePath = null;

        // TODO: Handle when someone does not pass an array or DefinitionSource. It is document thou...
        $this->definitions = $definitions instanceof DefinitionSource ? $definitions : new DefinitionSource($definitions);
    }

    public function enableCompiling(string|null $filePath = null, string|null $className = null): void
    {
        $this->enableCompiling = true;

        // A nice default below, put it in the configured temp folder.
        $this->compileFilePath = $filePath ?? sys_get_temp_dir() . '/CompiledContainer.php';

        // The default CompiledContainer class. It is in the global namespace.
        $this->compiledContainerClassName = $className ?? '\CompiledContainer';
    }

    public function writeCompiled(): void
    {
        if (!$this->enableCompiling) {
            throw new \RuntimeException('Compiling is not enabled');
        }

        if ($this->compileFilePath === null) {
            throw new \RuntimeException('No compile file path is defined');
        }

        if ($this->compiledContainerClassName === null) {
            throw new \RuntimeException('No compile container class name is defined');
        }

        $targetDir = dirname($this->compileFilePath);
        if (!is_dir($targetDir) || !is_writable($targetDir)) {
            throw new \RuntimeException('Directory of compiled file path ' . $targetDir . ' is not writable');
        }

        $tempFile = tempnam(sys_get_temp_dir(), basename($this->compileFilePath));
        if (!is_string($tempFile)) {
            throw new \RuntimeException('Failed to create temp file in configured temp dir');
        }

        $compiler = new Compiler($this->compiledContainerClassName);
        $fileContent = $compiler->compile($this->definitions);

        // put into the temp file with put contents
        file_put_contents($tempFile, $fileContent);

        // chmod before renaming
        // chmod the created compiled container adding the umask of the current runtime.
        // example: (umask=002) is 436 (664 in oct)
        // example: (umask=0022) is 420 (644 in oct)
        chmod($tempFile, 0666 & ~umask());

        // rename the temp file into the proper location
        // this ensures no partial file is picked up by
        // other processes
        rename($tempFile, $this->compileFilePath);

        // Clear the stat cache since we have created the file
        clearstatcache();

        if (function_exists('opcache_invalidate')
            && filter_var(ini_get('opcache.enable'), FILTER_VALIDATE_BOOLEAN)) {
            opcache_invalidate($this->compileFilePath);
        }
    }

    public function build(): Container
    {
        // Put the class into a variable so we can create it dynamically later.
        // (php does not support doing `new $this->containerClass`)
        $containerClass = Container::class;

        // Compiling is enabled, lets go.
        if ($this->enableCompiling
            && $this->compileFilePath !== null
            && $this->compiledContainerClassName !== null
            && is_file($this->compileFilePath)) {
            require $this->compileFilePath;
            $containerClass = $this->compiledContainerClassName;
        }

        return new $containerClass($this->definitions);
    }
}
