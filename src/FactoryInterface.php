<?php declare(strict_types=1);

namespace HbLib\Container;

use Ds\Map;

interface FactoryInterface
{
    /**
     * Resolves an entry by its name. A fresh instance will always be returned, never a singleton.
     *
     * @param string $name
     *
     *  @param array  $parameters
     *
     * @return mixed
     */
    public function make(string $name, array $parameters = []);
}
