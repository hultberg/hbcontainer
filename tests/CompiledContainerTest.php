<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

use HbLib\Container\ContainerBuilder;
use HbLib\Container\DefinitionSource;
use HbLib\Container\UnresolvedContainerException;
use PHPUnit\Framework\TestCase;
use function HbLib\Container\factory;
use function HbLib\Container\reference;
use function HbLib\Container\resolve;
use function HbLib\Container\value;

class CompiledContainerTest extends TestCase
{
    private function createTempFile()
    {
        // https://secure.php.net/manual/en/function.tmpfile.php#122678
        $tmpFile = stream_get_meta_data(tmpfile())['uri'];
        
        register_shutdown_function(function() use ($tmpFile) {
            if (file_exists($tmpFile)) {
                unlink($tmpFile);
            }
        });
        
        return $tmpFile;
    }
    
    private function getUniqueClassName(): string
    {
        return uniqid('CompiledContainer');
    }
    
    public function testCompileResolve()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => resolve(),
            Class2::class => resolve(),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
        
        self::assertInstanceOf(Class2::class, $container->get(Class2::class));
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));
    }
    
    public function testCompileFactoryWithInterfaceArgument()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class2::class => factory(function(Interface1 $interface) {
                return new Class1();
            }),
            Class100::class => resolve(),
            Interface1::class => reference(Class100::class),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
        
        self::assertInstanceOf(Class1::class, $container->get(Class2::class));
    }
    
    public function testCompileOptionalArguments()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => resolve(),
            Class200::class => resolve(),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
        
        self::assertInstanceOf(Class200::class, $container->get(Class200::class));
        self::assertNull($container->get(Class200::class)->someString);
        self::assertSame('12', $container->get(Class200::class)->moreStrings);
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
            Class10::class => factory(function($theName) {
                return new Class10($theName);
            })->parameter('theName', 'string'),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
        
        $container->get(Class1::class);
    
        self::assertInstanceOf(Class2::class, $container->get(Class2::class));
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));
        self::assertInstanceOf(Class10::class, $container->get(Class10::class));
        self::assertSame('string', $container->get(Class10::class)->name);
    }
    
    public function testCompileValue()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            'key1' => value('value1'),
            'key2' => value(['array1', 'array2']),
            'key3' => value(true),
            'key4' => value(false),
            'key5' => value('1'),
            'key6' => value(2),
            'key7' => value(2.0),
            'key8' => value(reference('key1')),
            'key9' => reference('key2'),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        
        $container = $containerBuilder->build();
        
        self::assertSame('value1', $container->get('key1'));
        self::assertSame('value1', $container->get('key8'));
        self::assertInternalType('array', $container->get('key2'));
        self::assertArraySubset(['array1', 'array2'], $container->get('key2'));
        self::assertTrue($container->get('key3'));
        self::assertFalse($container->get('key4'));
        self::assertSame('1', $container->get('key5'));
        self::assertSame(2, $container->get('key6'));
        self::assertSame(2.0, $container->get('key7'));
        self::assertInternalType('array', $container->get('key9'));
        self::assertArraySubset(['array1', 'array2'], $container->get('key9'));
    }
    
    public function testCompileFailParameter()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class10::class => factory(function(string $name) {
                return new Class10($name);
            }),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        
        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Unable to resolve parameter name on entity');
        $container = $containerBuilder->build();
    }
    
    public function testCompileFactoryChangedInRuntime()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => factory(function() {
                return new Class1();
            }),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
        
        $container->set(Class1::class, resolve(Class1::class));
        
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('Definition ' . Class1::class . ' is not a factory');
        $container->get(Class1::class);
    }
    
    public function testCompileReference()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            Class1::class => resolve(),
            Interface1::class => reference(Class1::class),
        ]));
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
    
        self::assertInstanceOf(Class1::class, $container->get(Interface1::class));
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));
    }
    
    public function testCompileNotDefinitionClass()
    {
        $source = new DefinitionSource([
            Interface1::class => reference(Class1::class),
            Class2::class => resolve(),
        ]);
        $containerBuilder = new ContainerBuilder($source);
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
    
        self::assertInstanceOf(Class1::class, $container->get(Interface1::class));
        self::assertInstanceOf(Class2::class, $container->get(Class2::class));
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
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
    
        self::assertSame('lol', $container->get('hei'));
        self::assertSame('null', $container->get('heisann'));
        self::assertFalse($container->get('lol2'));
        self::assertTrue($container->get('qs'));
        self::assertSame(0, $container->get('qs2'));
        self::assertSame(1, $container->get('qs3'));
        self::assertSame('', $container->get('qs4'));
    }
    
    public function testResolveOptionalInterface()
    {
        $containerBuilder = new ContainerBuilder(new DefinitionSource([
            SessionClass::class => resolve(),
        ]));
        
        $containerBuilder->enableCompiling($this->createTempFile(), $this->getUniqueClassName());
        $container = $containerBuilder->build();
        
        self::assertInstanceOf(SessionClass::class, $container->get(SessionClass::class));
        self::assertNull($container->get(SessionClass::class)->bag);
    }
}

class Class10 {
    public $name;
    function __construct(string $name) {
        $this->name = $name;
    }
}

class Class100 implements Interface1 {
    
}

class Class200 {
    public $class;
    public $someString;
    public $moreStrings;
    
    function __construct(Class1 $class1, $someString = null, $moreStrings = '12') {
        $this->class = $class1;
        $this->someString = $someString;
        $this->moreStrings = $moreStrings;
    }
}

interface InterfaceSessionBagCompiled {}

class SessionClass {
    public $bag;
    
    function __construct(InterfaceSessionBagCompiled $bag = null) {
        $this->bag = $bag;
    }
}
