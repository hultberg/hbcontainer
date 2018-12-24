<?php declare(strict_types=1);

/**
 * @project hbcontainer
 */

namespace HbLib\Container;

class CompiledEntry
{
    /**
     * @var string
     */
    private $id;

    /**
     * @var mixed
     */
    private $value;

    /**
     * CompiledEntry constructor.
     * @param string $id
     * @param mixed $value
     */
    public function __construct(string $id, $value = null)
    {
        $this->id = $id;
        $this->value = $value ?? $id;
    }

    /**
     * @return string
     */
    public function getId(): string
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getValue()
    {
        return $this->value;
    }
}
