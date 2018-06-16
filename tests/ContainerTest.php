<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

use Psr\Container\NotFoundExceptionInterface;
use HbLib\Container\Container;
use HbLib\Container\InvokeException;
use HbLib\Container\UnresolvedContainerException;
use PHPUnit\Framework\TestCase;
use function HbLib\Container\factory;
use function HbLib\Container\get;

class ContainerTest extends TestCase
{
    public function testGet()
    {
        $container = new Container([
            'key' => 'value',
        ]);
        $this->assertEquals('value', $container->get('key'));
    }

    public function testGetFactory()
    {
        $container = new Container([
            'key' => factory(function() { return 12; }),
        ]);
        $this->assertEquals(12, $container->get('key'));
    }

    public function testGetPreviousFactory()
    {
        $container = new Container([
            'key' => function() { return 12; },
        ]);
        $this->assertEquals(12, $container->get('key'));
    }

    public function testGetObject()
    {
        $container = new Container([
            'key' => get(Class1::class),
        ]);
        $this->assertInstanceOf(Class1::class, $container->get('key'));
    }

    public function testDefinitionFactoryCallableArray()
    {
        $class1 = new ClassWithMethod();

        $container = new Container([
            'key' => factory([$class1, 'callMe']),
        ]);

        $this->assertInstanceOf(Class1::class, $container->get('key'));
    }

    public function testDefinitionClassWithDefinitionParameter()
    {
        $container = new Container([
            'class1' => get(Class1::class),
            'class2' => get(Class2::class)->parameter('class1', get('class1')),
        ]);

        $this->assertInstanceOf(Class1::class, $container->get('class2')->class1);
    }

    public function testDefinitionClassWithParameters()
    {
        $container = new Container([
            'key' => get(Class2OptionalValue::class)->parameter('value', 'lol'),
        ]);

        $this->assertEquals('lol', $container->get('key')->value);
    }

    public function testDefinitionClassWithParametersIsNotSingletons()
    {
        // Definitions with a parameter does not use a singleton.
        $container = new Container([
            'key1' => get(Class2OptionalValue::class),
            'key2' => get(Class2OptionalValue::class)->parameter('value', 'lol'),
        ]);

        $container->get('key2');

        $class2 = $container->get('key1');
        $class2->value = 'singleton';

        $this->assertEquals('lol', $container->get('key2')->value);
    }

    public function testDefinitionClassNoParameters()
    {
        $container = new Container([
            'key' => get(Class2OptionalValue::class),
        ]);

        $this->assertEquals('test', $container->get('key')->value);
    }

    public function testDefinitionClassNoParametersIsSingletons()
    {
        $container = new Container([
            'key' => get(Class2OptionalValue::class),
        ]);

        $class2 = $container->get('key');
        $class2->value = 'singleton';

        $this->assertEquals('singleton', $container->get('key')->value);
    }

    public function testGetIsSingleton()
    {
        $container = new Container([
            'key' => factory(function() {
                $class = new \stdClass;
                $class->test = true;
                return $class;
            }),
        ]);

        $this->assertInstanceOf(\stdClass::class, $container->get('key'));
        $this->assertInternalType('bool', $container->get('key')->test);
        $this->assertTrue($container->get('key')->test);

        $container->get('key')->test = 5;
        $this->assertInternalType('int', $container->get('key')->test);
        $this->assertEquals(5, $container->get('key')->test);
    }

    public function testHas()
    {
        $container = new Container([
            'key' => factory(function() {
                $class = new \stdClass;
                $class->test = true;
                return $class;
            }),
            'key1' => null,
        ]);

        $this->assertTrue($container->has('key'));
        $this->assertTrue($container->has('key1'));
        $this->assertFalse($container->has('key2'));
    }

    public function testCallBasic()
    {
        $container = new Container();

        $someClassInstance = new ClassWithMethod();
        $this->assertInstanceOf(Class1::class, $container->call([$someClassInstance, 'callMe']));
    }

    public function testCallStatic()
    {
        $container = new Container();

        $this->assertInstanceOf(Class1::class, $container->call([ClassWithMethod::class, 'callMe']));
    }

    public function testCallNonExistingMethod()
    {
        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Method callMeMaybe does not exist on class');

        $container = new Container();

        $someClassInstance = new ClassWithMethod();
        $this->assertInstanceOf(Class1::class, $container->call([$someClassInstance, 'callMeMaybe']));
    }

    public function testCallCallable()
    {
        $container = new Container();
        $callable = function(Class1 $class) {
            return $class;
        };

        $this->assertInstanceOf(Class1::class, $container->call($callable));
    }

    public function testCallUnsupportedFormat()
    {
        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Unsupported format.');

        $container = new Container();
        $container->call('callMeMaybe');
        $this->assertFalse(true);
    }

    public function testCallNotInvokeable()
    {
        $this->expectException(InvokeException::class);
        $this->expectExceptionMessage('Unable to invoke non-object instance.');

        $container = new Container();

        $this->assertInstanceOf(Class1::class, $container->call([1, 'callMe']));
    }

    public function testClassNameExists()
    {
        $container = new Container();

        $ref = new \ReflectionClass(Container::class);
        $classExistsMethod = $ref->getMethod('classNameExists');
        $classExistsMethod->setAccessible(true);

        $this->assertFalse($classExistsMethod->invoke($container, 'SomeClassThatDoNotExist'));
    }

    public function testMakeNotSingleton()
    {
        $container = new Container([
            'key' => factory(function() {
                $class = new \stdClass;
                $class->test = true;
                return $class;
            }),
        ]);

        $this->assertInstanceOf(\stdClass::class, $container->make('key'));
        $this->assertInternalType('bool', $container->make('key')->test);
        $this->assertTrue($container->make('key')->test);

        $container->make('key')->test = 5;
        $this->assertInternalType('bool', $container->make('key')->test);
        $this->assertTrue($container->make('key')->test);
    }

    public function testMakeInterfaceRequiredDependency()
    {
        // Class2RequireInterface1 depends on an interface not defined in the
        // definitions so the parameter resolving must throw an exception.

        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Unable to resolve parameter interface on entity HbLib\Container\Tests\Class2RequireInterface1');

        $container = new Container();
        $container->make(Class2RequireInterface1::class);
        $this->assertFalse(true);
    }

    public function testMakeInterfaceOptionalDependency()
    {
        // Almost the same as the previous test, but the parameter is not required.

        $container = new Container();
        $this->assertNull($container->make(Class2OptionalInterface1::class)->interface);
    }

    public function testMakeNonExistingClass()
    {
        $this->expectException(NotFoundExceptionInterface::class);
        $this->expectExceptionMessage('Class SomeClassThatDoNotExists does not exist');

        $container = new Container();
        $container->make('SomeClassThatDoNotExists');
        $this->assertFalse(true);
    }

    public function testMakeAbstract()
    {
        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Cant create an instance of an abstract class.');

        $container = new Container();
        $container->make(AbstractClass1::class);
        $this->assertFalse(true);
    }

    public function testMakeInterface()
    {
        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Cant create an instance of an interface.');

        $container = new Container();
        $container->make(Interface1::class);
        $this->assertFalse(true);
    }

    public function testMakeResolveDependency()
    {
        $container = new Container();

        $this->assertInstanceOf(Class2::class, $container->make(Class2::class));
        $this->assertInstanceOf(Class1::class, $container->make(Class2::class)->class1);
        $this->assertEquals('init', $container->make(Class2::class)->class1->parameter);
    }

    public function testMakeResolveDependencyInjectParameter()
    {
        $container = new Container();

        $class1Instance = new Class1();
        $class1Instance->parameter = 'otherValue';

        $class2 = $container->make(Class2::class, array('class1' => $class1Instance));

        $this->assertInstanceOf(Class2::class, $class2);
        $this->assertInstanceOf(Class1::class, $class2->class1);
        $this->assertEquals('otherValue', $class2->class1->parameter);
    }

    public function testResolvesWithDefault()
    {
        $container = new Container();

        $class = $container->get(ClassWithDefaultArgument::class);

        $this->assertInstanceOf(ClassWithDefaultArgument::class, $class);
    }

    public function testSet()
    {
        $container = new Container();
        $container->set(Class1::class, new Class1());
        $this->assertInstanceOf(Class1::class, $container->get(Class1::class));

        $container->set('hei2', factory(function() {
            return 'hei';
        }));

        $this->assertEquals('hei', $container->get('hei2'));
    }
}
