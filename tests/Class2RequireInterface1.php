<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class Class2RequireInterface1
{
    private $interface;
    
    public function __construct(Interface1 $interface)
    {
        $this->interface = $interface;
    }
}