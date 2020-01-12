<?php

declare(strict_types=1);        

namespace HbLib\Container;

class SingletonReference extends ObjectReference
{
    private object $object;
    
    public function __construct(object $object)
    {
        $this->object = $object;
    }
    
    public function get(): object
    {
        return $this->object;
    }
}