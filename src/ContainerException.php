<?php declare(strict_types=1);
/**
 * @project hbcontainer
 */

namespace HbLib\Container;

use Psr\Container\ContainerExceptionInterface;

class ContainerException extends \Exception implements ContainerExceptionInterface
{
    public static function invalidInstanceToInvoke()
    {

    }
}