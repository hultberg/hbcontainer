<?php declare(strict_types=1);

namespace HbLib\Container;

use Psr\Container\ContainerExceptionInterface;

class UnresolvedContainerException extends \Exception implements ContainerExceptionInterface
{

}
