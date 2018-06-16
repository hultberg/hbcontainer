<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class ClassWithDefaultArgument
{
    private $bag;

    public function __construct(array $bag = array())
    {
        $this->bag = $bag;
    }
}
