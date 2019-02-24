<?php

declare(strict_types=1);

namespace HbLib\Container;

use Ds\Map;
use OutOfBoundsException;

class DefinitionSource implements \Countable, \IteratorAggregate
{
    /**
     * @var Map
     */
    private $definitions;

    /**
     * @param array|Map|null $definitions
     */
    public function __construct($definitions = null)
    {
        if (is_array($definitions)) {
            $definitions = new Map($definitions);
        }

        $this->definitions = $definitions ?? new Map();
    }

    /**
     * @param string|mixed $id
     * @param AbstractDefinition|mixed $value
     */
    public function set($id, $value): void
    {
        $this->definitions->put($id, $value);
    }

    /**
     * @param string|mixed $id
     * @return AbstractDefinition|mixed
     */
    public function get($id)
    {
        return $this->definitions->get($id, null);
    }

    /**
     * @param string|mixed $id
     * @return bool
     */
    public function has($id): bool
    {
        return $this->definitions->hasKey($id);
    }

    /**
     * @return Map
     */
    public function all(): Map
    {
        return $this->definitions;
    }

    public function count()
    {
        return $this->definitions->count();
    }

    public function getIterator()
    {
        yield from $this->definitions;
    }

    /**
     * @deprecated
     * @see all()
     * @return Map
     */
    public function getDefinitions(): Map
    {
        return $this->definitions;
    }

    /**
     * @deprecated
     * @see has()
     * @param mixed $id
     * @return bool
     */
    public function hasDefinition($id): bool
    {
        return $this->definitions->hasKey($id);
    }

    /**
     * @deprecated
     * @see get()
     * @param mixed $id
     * @return mixed
     */
    public function getDefinition($id)
    {
        return $this->definitions->get($id, null);
    }

    /**
     * @deprecated Deprecated in favour of set()
     * @see set()
     * @param mixed $id
     * @param mixed $value
     */
    public function setDefinition($id, $value): void
    {
        $this->definitions->put($id, $value);
    }
}
