<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

use HbLib\Container\Container;
use HbLib\Container\ContainerBuilder;
use HbLib\Container\DefinitionSource;
use PHPUnit\Framework\TestCase;
use function HbLib\Container\factory;
use function HbLib\Container\reference;
use function HbLib\Container\resolve;

class ContainerBuilderTest extends TestCase
{
    public function testBuild()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => resolve(),
            Class2::class => resolve(),
        ]));
        $container = $containerBuilder->build();
        
        self::assertInstanceOf(Container::class, $container);
    }
}
