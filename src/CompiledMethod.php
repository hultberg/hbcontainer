<?php declare(strict_types=1);

/**
 * @project hbcontainer
 */

namespace HbLib\Container;

class CompiledMethod
{
    public function __construct(
        private string $name,
        private string $content,
        private string $returnType = 'mixed',
    ) { }

    public function getName(): string
    {
        return $this->name;
    }

    public function getContent(): string
    {
        return $this->content;
    }

    public function getReturnType(): string
    {
        return $this->returnType;
    }
}
