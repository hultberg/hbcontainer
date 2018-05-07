<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class Class2OptionalValue
{
    public $value;
    
    public function __construct($value = 'test')
    {
        $this->value = $value;
    }
}