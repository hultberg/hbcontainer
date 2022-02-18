<?php declare(strict_types=1);

namespace HbLib\Container\Tests;

use HbLib\Container\DefinitionSource;
use HbLib\Container\ContainerException;
use HbLib\Container\CircularDependencyException;
use Psr\Container\NotFoundExceptionInterface;
use HbLib\Container\Container;
use HbLib\Container\InvokeException;
use HbLib\Container\UnresolvedContainerException;
use PHPUnit\Framework\TestCase;
use ReflectionException;

use function HbLib\Container\factory;
use function HbLib\Container\resolve;
use function HbLib\Container\reference;
use function HbLib\Container\value;

class ContainerTest extends TestCase
{
    public function testGet()
    {
        $container = new Container(new DefinitionSource([
            'key' => value('value'),
        ]));
        self::assertEquals('value', $container->get('key'));
    }

    public function testReferenceToNonClass()
    {
        $container = new Container(new DefinitionSource([
            'key' => value('value'),
            'someKey' => reference('key'),
        ]));

        self::assertEquals('value', $container->get('someKey'));
    }

    public function testValue()
    {
        $container = new Container(new DefinitionSource([
            'someKey' => value('key'),
        ]));

        self::assertEquals('key', $container->get('someKey'));
    }

    public function testValueAnotherDefinition()
    {
        $container = new Container(new DefinitionSource([
            'key' => value('value'),
            'someKey' => value(reference('key')),
        ]));

        self::assertEquals('value', $container->get('someKey'));
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
            'key' => factory(function() { return 12; }),
        ]));
        self::assertEquals(12, $container->get('key'));
    }

    public function testGetValues()
    {
        $container = new Container(new DefinitionSource([
            'hei' => value('lol'),
            'heisann' => factory(function() {
                return 'null';
            }),
            'lol2' => value(false),
            'qs' => value(true),
            'qs2' => value(0),
            'qs3' => value(1),
            'qs4' => value(''),
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
        // $this->expectException(ContainerException::class);
        // $this->expectExceptionMessage('Circular dependency detected while resolving entry ' . Circular1::class);
        //
        $container = new Container(new DefinitionSource([
            Circular1::class => resolve(),
            Circular2::class => resolve(),
        ]));

        try {
            self::assertNotInstanceOf(Circular1::class, $container->get(Circular1::class));
        } catch (ContainerException $e) {
            self::assertTrue(true);

            do {
                if ($e instanceof CircularDependencyException) {
                    self::assertTrue(true);
                    self::assertSame('Circular dependency detected while resolving entry HbLib\Container\Tests\Circular1', $e->getMessage());
                    break;
                }

                $e = $e->getPrevious();
            } while ($e !== null);
        }
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
            })->asSingleton(),
        ]));

        self::assertInstanceOf(\stdClass::class, $container->get('key'));
        self::assertIsBool($container->get('key')->test);
        self::assertTrue($container->get('key')->test);

        $container->get('key')->test = 5;
        self::assertIsInt($container->get('key')->test);
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
        $this->expectException(ReflectionException::class);
        $this->expectExceptionMessage('Class "1" does not exist');

        $container = new Container();

        self::assertInstanceOf(Class1::class, $container->call([1, 'callMe']));
    }

    public function testClassNameExists()
    {
        self::assertFalse(\HbLib\Container\classNameExists('SomeClassThatDoNotExist'));
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
        self::assertIsBool($container->make('key')->test);
        self::assertTrue($container->make('key')->test);

        $container->make('key')->test = 5;
        self::assertIsBool($container->make('key')->test);
        self::assertTrue($container->make('key')->test);
    }

    public function testMakeInterfaceRequiredDependency()
    {
        // Class2RequireInterface1 depends on an interface not defined in the
        // definitions so the parameter resolving must throw an exception.

        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Unable to resolve parameter interface on class HbLib\Container\Tests\Class2RequireInterface1');

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
        $this->expectExceptionMessage('Class "SomeClassThatDoNotExists" does not exist');

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

    public function testArgumentBuiltin()
    {
        $container = new Container(new DefinitionSource([
            'key' => factory(function(string $name) {
                return new \stdClass;
            }),
        ]));

        $this->expectException(UnresolvedContainerException::class);
        $this->expectExceptionMessage('Unable to resolve parameter name on entity');
        $container->get('key');
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

    public function testEnsureSingletonCache()
    {
        $container = new Container();
        $needsNeedsRand = $container->get(NeedsNeedsWithRandom::class);
        $needsRand = $container->get(NeedsWithRandom::class);
        $rand = $container->get(WithRandom::class);

        self::assertSame($rand->int, $needsNeedsRand->rand->rand->int);
        self::assertSame($rand->int, $needsRand->rand->int);
        self::assertSame($needsRand->rand->int, $needsNeedsRand->rand->rand->int);
    }

    public function testResolveOptionalInterface()
    {
        $container = new Container(new DefinitionSource([
            Session::class => resolve(),
        ]));

        self::assertInstanceOf(Session::class, $container->get(Session::class));
        self::assertNull($container->get(Session::class)->bag);
    }
}

class Circular1 {
    function __construct(Circular2 $class) {
        $class = 'lol';
    }
}

class Circular2 {
    function __construct(Circular1 $class) {
        $class = 'lol';
    }
}

class WithRandom {
    public $int;

    function __construct() {
        $this->int = microtime(true) + random_int(100, 50000);
    }
}

class NeedsWithRandom {
    public $rand;

    function __construct(WithRandom $random) {
        $this->rand = $random;
    }
}

class NeedsNeedsWithRandom {
    public $rand;

    function __construct(NeedsWithRandom $random) {
        $this->rand = $random;
    }
}

interface InterfaceSessionBag {}

class Session {
    public $bag;

    function __construct(InterfaceSessionBag $bag = null) {
        $this->bag = $bag;
    }
}
