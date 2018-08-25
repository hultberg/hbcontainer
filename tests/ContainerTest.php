<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

use HbLib\Container\DefinitionSource;
use HbLib\Container\ContainerException;

use Psr\Container\NotFoundExceptionInterface;
use HbLib\Container\Container;
use HbLib\Container\InvokeException;
use HbLib\Container\UnresolvedContainerException;
use PHPUnit\Framework\TestCase;
use function HbLib\Container\factory;
use function HbLib\Container\resolve;
use function HbLib\Container\reference;

class ContainerTest extends TestCase
{
    public function testGet()
    {
        $container = new Container(new DefinitionSource([
            'key' => 'value',
        ]));
        self::assertEquals('value', $container->get('key'));
    }

    public function testGetFactory()
    {
        $container = new Container(new DefinitionSource([
            'key' => factory(function() { return 12; }),
        ]));
        self::assertEquals(12, $container->get('key'));
    }
    
    public function testGetFactoryParameter()
    {
        $container = new Container(new DefinitionSource([
            'key' => factory(function(Class1 $instance) { return 12; })->parameter('instance', resolve(Class1::class)),
        ]));
        self::assertEquals(12, $container->get('key'));
    }

    public function testGetPreviousFactory()
    {
        $container = new Container(new DefinitionSource([
            'key' => function() { return 12; },
        ]));
        self::assertEquals(12, $container->get('key'));
    }
    
    public function testGetNonDefinitions()
    {
        $container = new Container(new DefinitionSource([
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
        
        self::assertSame('lol', $container->get('hei'));
        self::assertSame('null', $container->get('heisann'));
        self::assertFalse($container->get('lol2'));
        self::assertTrue($container->get('qs'));
        self::assertSame(0, $container->get('qs2'));
        self::assertSame(1, $container->get('qs3'));
        self::assertSame('', $container->get('qs4'));
    }

    public function testGetObject()
    {
        $container = new Container(new DefinitionSource([
            'key' => resolve(Class1::class),
        ]));
        self::assertInstanceOf(Class1::class, $container->get('key'));
    }
    
    public function testCatchesCircular()
    {
        $this->expectException(ContainerException::class);
        $this->expectExceptionMessage('Circular dependency detected while resolving entry ' . Circular1::class);
        
        $container = new Container(new DefinitionSource([
            Circular1::class => resolve(),
            Circular2::class => resolve(),
        ]));
        self::assertNotInstanceOf(Circular1::class, $container->get(Circular1::class));
    }

    public function testDefinitionFactoryCallableArray()
    {
        $class1 = new ClassWithMethod();
        
        $container = new Container(new DefinitionSource([
            'key' => factory([$class1, 'callMe']),
        ]));

        self::assertInstanceOf(Class1::class, $container->get('key'));
    }

    public function testDefinitionClassWithDefinitionParameter()
    {
        $container = new Container(new DefinitionSource([
            'class1' => resolve(Class1::class),
            'class2' => resolve(Class2::class)->parameter('class1', reference('class1')),
        ]));

        self::assertInstanceOf(Class1::class, $container->get('class2')->class1);
    }

    public function testDefinitionClassWithParameters()
    {
        $container = new Container(new DefinitionSource([
            'key' => resolve(Class2OptionalValue::class)->parameter('value', 'lol'),
        ]));

        self::assertEquals('lol', $container->get('key')->value);
    }

    public function testDefinitionClassWithParametersIsNotSingletons()
    {
        // Definitions with a parameter does not use a singleton.
        $container = new Container(new DefinitionSource([
            'key1' => resolve(Class2OptionalValue::class),
            'key2' => resolve(Class2OptionalValue::class)->parameter('value', 'lol'),
        ]));

        $container->get('key2');

        $class2 = $container->get('key1');
        $class2->value = 'singleton';

        self::assertEquals('lol', $container->get('key2')->value);
    }

    public function testDefinitionClassNoParameters()
    {
        $container = new Container(new DefinitionSource([
            'key' => resolve(Class2OptionalValue::class),
        ]));

        self::assertEquals('test', $container->get('key')->value);
    }

    public function testDefinitionClassNoParametersIsSingletons()
    {
        $container = new Container(new DefinitionSource([
            'key' => resolve(Class2OptionalValue::class),
        ]));

        $class2 = $container->get('key');
        $class2->value = 'singleton';

        self::assertEquals('singleton', $container->get('key')->value);
    }

    public function testGetIsSingleton()
    {
        $container = new Container(new DefinitionSource([
            'key' => factory(function() {
                $class = new \stdClass;
                $class->test = true;
                return $class;
            }),
        ]));

        self::assertInstanceOf(\stdClass::class, $container->get('key'));
        self::assertInternalType('bool', $container->get('key')->test);
        self::assertTrue($container->get('key')->test);

        $container->get('key')->test = 5;
        self::assertInternalType('int', $container->get('key')->test);
        self::assertEquals(5, $container->get('key')->test);
    }

    public function testHas()
    {
        $container = new Container(new DefinitionSource([
            'key' => factory(function() {
                $class = new \stdClass;
                $class->test = true;
                return $class;
            }),
            'key1' => null,
        ]));

        self::assertTrue($container->has('key'));
        self::assertTrue($container->has('key1'));
        self::assertFalse($container->has('key2'));
    }

    public function testCallBasic()
    {
        $container = new Container();

        $someClassInstance = new ClassWithMethod();
        self::assertInstanceOf(Class1::class, $container->call([$someClassInstance, 'callMe']));
    }

    public function testCallStatic()
    {
        $container = new Container();

        self::assertInstanceOf(Class1::class, $container->call([ClassWithMethod::class, 'callMe']));
    }

    public function testCallNonExistingMethod()
    {
        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Method callMeMaybe does not exist on class');

        $container = new Container();

        $someClassInstance = new ClassWithMethod();
        self::assertInstanceOf(Class1::class, $container->call([$someClassInstance, 'callMeMaybe']));
    }

    public function testCallCallable()
    {
        $container = new Container();
        $callable = function(Class1 $class) {
            return $class;
        };

        self::assertInstanceOf(Class1::class, $container->call($callable));
    }

    public function testCallUnsupportedFormat()
    {
        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Unsupported format.');

        $container = new Container();
        $container->call('callMeMaybe');
        self::assertFalse(true);
    }

    public function testCallNotInvokeable()
    {
        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Unable to invoke non-object instance.');

        $container = new Container();

        self::assertInstanceOf(Class1::class, $container->call([1, 'callMe']));
    }

    public function testClassNameExists()
    {
        $container = new Container();

        $ref = new \ReflectionClass(Container::class);
        $classExistsMethod = $ref->getMethod('classNameExists');
        $classExistsMethod->setAccessible(true);

        self::assertFalse($classExistsMethod->invoke($container, 'SomeClassThatDoNotExist'));
    }

    public function testMakeNotSingleton()
    {
        $container = new Container(new DefinitionSource([
            'key' => factory(function() {
                $class = new \stdClass;
                $class->test = true;
                return $class;
            }),
        ]));

        self::assertInstanceOf(\stdClass::class, $container->make('key'));
        self::assertInternalType('bool', $container->make('key')->test);
        self::assertTrue($container->make('key')->test);

        $container->make('key')->test = 5;
        self::assertInternalType('bool', $container->make('key')->test);
        self::assertTrue($container->make('key')->test);
    }

    public function testMakeInterfaceRequiredDependency()
    {
        // Class2RequireInterface1 depends on an interface not defined in the
        // definitions so the parameter resolving must throw an exception.

        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Unable to resolve parameter interface on entity HbLib\Container\Tests\Class2RequireInterface1');

        $container = new Container();
        $container->make(Class2RequireInterface1::class);
        self::assertFalse(true);
    }

    public function testMakeInterfaceOptionalDependency()
    {
        // Almost the same as the previous test, but the parameter is not required.

        $container = new Container();
        self::assertNull($container->make(Class2OptionalInterface1::class)->interface);
    }

    public function testMakeNonExistingClass()
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Class SomeClassThatDoNotExists does not exist');

        $container = new Container();
        $container->make('SomeClassThatDoNotExists');
        self::assertFalse(true);
    }

    public function testMakeAbstract()
    {
        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Cant create an instance of an abstract class.');

        $container = new Container();
        $container->make(AbstractClass1::class);
        self::assertFalse(true);
    }

    public function testMakeInterface()
    {
        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Cant create an instance of an interface.');

        $container = new Container();
        $container->make(Interface1::class);
        self::assertFalse(true);
    }

    public function testMakeResolveDependency()
    {
        $container = new Container();

        self::assertInstanceOf(Class2::class, $container->make(Class2::class));
        self::assertInstanceOf(Class1::class, $container->make(Class2::class)->class1);
        self::assertEquals('init', $container->make(Class2::class)->class1->parameter);
    }

    public function testMakeResolveDependencyInjectParameter()
    {
        $container = new Container();

        $class1Instance = new Class1();
        $class1Instance->parameter = 'otherValue';

        $class2 = $container->make(Class2::class, array('class1' => $class1Instance));

        self::assertInstanceOf(Class2::class, $class2);
        self::assertInstanceOf(Class1::class, $class2->class1);
        self::assertEquals('otherValue', $class2->class1->parameter);
    }

    public function testResolvesWithDefault()
    {
        $container = new Container();

        $class = $container->get(ClassWithDefaultArgument::class);

        self::assertInstanceOf(ClassWithDefaultArgument::class, $class);
    }

    public function testSet()
    {
        $container = new Container();
        $container->set(Class1::class, new Class1());
        self::assertInstanceOf(Class1::class, $container->get(Class1::class));

        $container->set('hei2', factory(function() {
            return 'hei';
        }));

        self::assertEquals('hei', $container->get('hei2'));
    }
}

class Circular1 {
    function __construct(Circular2 $class) {
        
    }
}

class Circular2 {
    function __construct(Circular1 $class) {
        
    }
}
