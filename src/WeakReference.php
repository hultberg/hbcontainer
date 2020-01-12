<?php

declare(strict_types=1);        

namespace HbLib\Container;

use WeakReference as PhpWeakReference;

class WeakReference extends ObjectReference
{
    private PhpWeakReference $ref;
    
    public function __construct(object $ref)
    {
        $this->ref = PhpWeakReference::create($ref);
    }
    
    public function get()
    {
        return $this->ref->get();
    }
}