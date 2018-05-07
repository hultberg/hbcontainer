<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

class ClassWithMethod
{
    public function callMe(Class1 $class1)
    {
        return $class1;
    }
    
    public static function callMeStatic(Class1 $class1)
    {
        return $class1;
    }
}