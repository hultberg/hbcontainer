<?php declare(strict_types=1);

namespace HbLib\Container;

abstract class AbstractDefinition
{
    abstract public function getTypeName(): string;
}