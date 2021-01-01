<?php

declare(strict_types=1);

namespace HbLib\Container;

use Countable;
use IteratorAggregate;
use function array_key_exists;
use function count;

/**
 * Class DefinitionSource
 * @package HbLib\Container
 * @phpstan-implements IteratorAggregate<string, AbstractDefinition>
 */
class DefinitionSource implements Countable, IteratorAggregate
{
    /**
     * @var array<string, AbstractDefinition>
     */
    private array $definitions;

    /**
     * @param array<string, AbstractDefinition> $definitions
     */
    public function __construct(array $definitions = [])
    {
        $this->definitions = $definitions;
    }

    public function set(string $id, AbstractDefinition $value): void
    {
        $this->definitions[$id] = $value;
    }

    public function get(string $id): ?AbstractDefinition
    {
        return $this->definitions[$id] ?? null;
    }

    public function has(string $id): bool
    {
        return array_key_exists($id, $this->definitions);
    }

    /**
     * @return array<string, AbstractDefinition>
     */
    public function all(): array
    {
        return $this->definitions;
    }

    public function getIterator()
    {
        yield from $this->definitions;
    }

    /**
     * @return int
     */
    public function count()
    {
        return count($this->definitions);
    }
}
