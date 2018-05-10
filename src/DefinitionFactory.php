<?php declare(strict_types=1);

namespace HbLib\Container;

use Closure;

class DefinitionFactory extends AbstractDefinition
{
    /**
     * @var Closure
     */
    private $closure;

    /**
     * @param Closure $closure
     */
    public function __construct(Closure $closure)
    {
        $this->closure = $closure;
    }

    public static function fromCallable($callable)
    {
        if (is_array($callable)) {
            $callable = function(InvokerInterface $invoker) use ($callable) {
                return $invoker->call($callable);
            };
        }

        return new self(Closure::fromCallable($callable));
    }

    /**
     * @return Closure
     */
    public function getClosure(): Closure
    {
        return $this->closure;
    }
}
