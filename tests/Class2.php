<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class Class2
{
    /**
     * @var Class1
     */
    public $class1;
    
    public function __construct(Class1 $class1)
    {
        $this->class1 = $class1;
    }
}