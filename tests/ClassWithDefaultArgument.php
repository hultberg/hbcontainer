<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class ClassWithDefaultArgument
{
    public function __construct(array $bag = array())
    {
    }
}
