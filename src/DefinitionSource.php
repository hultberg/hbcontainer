<?php

declare(strict_types=1);

namespace HbLib\Container;

use function array_key_exists;
use function count;

class DefinitionSource implements \Countable, \IteratorAggregate
{
    /**
     * @var array
     */
    private $definitions;

    /**
     * @param array $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions;
    }

    /**
     * @param string|mixed $id
     * @param AbstractDefinition|mixed $value
     */
    public function set($id, $value): void
    {
        $this->definitions[$id] = $value;
    }

    /**
     * @param string|mixed $id
     * @return AbstractDefinition|mixed
     */
    public function get($id)
    {
        return $this->definitions[$id] ?? null;
    }

    /**
     * @param string|mixed $id
     * @return bool
     */
    public function has($id): bool
    {
        return array_key_exists($id, $this->definitions);
    }

    /**
     * @return array
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function count()
    {
        return count($this->definitions);
    }

    public function getIterator()
    {
        yield from $this->definitions;
    }
}
