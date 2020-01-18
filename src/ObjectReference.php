<?php

declare(strict_types=1);

namespace HbLib\Container;

abstract class ObjectReference
{
    public abstract function get(): ?object;
}
