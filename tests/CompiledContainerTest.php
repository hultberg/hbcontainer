<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

use HbLib\Container\ContainerBuilder;
use HbLib\Container\DefinitionSource;
use PHPUnit\Framework\TestCase;
use function HbLib\Container\factory;
use function HbLib\Container\reference;
use function HbLib\Container\resolve;

class CompiledContainerTest extends TestCase
{
    private function createTempFile()
    {
        // https://secure.php.net/manual/en/function.tmpfile.php#122678
        $tmpFile = tmpfile();
        return stream_get_meta_data($tmpFile)['uri'];
    }
    
    public function testCompileResolve()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => resolve(),
            Class2::class => resolve(),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile());
        $container = $containerBuilder->build();
        
        self::assertInstanceOf(Class2::class, $container->get(Class2::class));
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));
    }
    
    public function testCompileFactory()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => factory(function() {
                return new Class1();
            }),
            Class2::class => factory(function(Class1 $class) {
                return new Class2($class);
            }),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile());
        $container = $containerBuilder->build();
    
        self::assertInstanceOf(Class2::class, $container->get(Class2::class));
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));
    }
    
    public function testCompileReference()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => resolve(),
            Interface1::class => reference(Class1::class),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile());
        $container = $containerBuilder->build();
    
        self::assertInstanceOf(Class1::class, $container->get(Interface1::class));
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));
    }
    
    public function testCompileNonDefinitions()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            'hei' => 'lol',
            'heisann' => function() {
                return 'null';
            },
            'lol2' => false,
            'qs' => true,
            'qs2' => 0,
            'qs3' => 1,
            'qs4' => '',
        ]));
        $containerBuilder->enableCompiling($this->createTempFile());
        $container = $containerBuilder->build();
    
        self::assertSame('lol', $container->get('hei'));
        self::assertSame('null', $container->get('heisann'));
        self::assertFalse($container->get('lol2'));
        self::assertTrue($container->get('qs'));
        self::assertSame(0, $container->get('qs2'));
        self::assertSame(1, $container->get('qs3'));
        self::assertSame('', $container->get('qs4'));
    }
}
