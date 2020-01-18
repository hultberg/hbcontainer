<?php

declare(strict_types=1);

namespace HbLib\Container;

use WeakReference as PhpWeakReference;

class WeakReference extends ObjectReference
{
    /**
     * @var PhpWeakReference<object>
     */
    private PhpWeakReference $ref;

    public function __construct(object $ref)
    {
        $this->ref = PhpWeakReference::create($ref);
    }

    public function get(): ?object
    {
        return $this->ref->get();
    }
}
