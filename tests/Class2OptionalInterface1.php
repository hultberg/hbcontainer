<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class Class2OptionalInterface1
{
    public $interface;
    
    public function __construct(Interface1 $interface = null)
    {
        $this->interface = $interface;
    }
}