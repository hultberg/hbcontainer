# HbContainer

A PSR-11 lightweight dependency injection container for all types of projects. Supports php 8.0 and newer.

## Installation

With composer:
```
composer require edvin/hbcontainer
```

## Resolving

HbContainer uses `definitions` to define entries to be resolved by the container. The container can resolve any class that can be constructed with resolvable dependencies on its own. Resolved definitions are by default stored as weak references and can be set as singletons which will use a normal reference.

## Getting entries

The HbContainer has three primary methods to get/call entries:

1. `get`: Resolve and runtime-caches the resolved value for the next call.
1. `make`: Always resolve and return a new instance.
1. `call`: Resolve the provided argument as something to call and resolve its parameters.

The HbContainer takes an `DefinitionSource` that contains several definitions that tell the container how to resolve the specific entry. The different types are:

1. `DefinitionFactory`: An definition with an closure to return the item in its final state. The result is added to the singleton cache. Helper function: `\HbLib\Container\factory`
2. `DefinitionClass`: An definition to construct a class with or without provided parameters. If any parameters are defined, the class is never runtime-cached. Helper function: `\HbLib\Container\resolve`. Its optional to provide an class to this definition, and if none is provided it will assume the definition ID (index of the definition array) is the class name. See usage example below.
2. `DefinitionReference`: An definition to reference another definition entry, you may not provide parameters to this definition. Helper function: `\HbLib\Container\reference`.
2. `DefinitionValue`: An definition to return some value. The value itself can be another definition. Helper function: `\HbLib\Container\value`.

## Interfaces

When depending on the container, it is recommended to depend on the provided interfaces:

1. `\Psr\Container\ContainerInteface`: Provides the `get` method.
1. `\HbLib\Container\FactoryInterface`: Provides the `make` mehod.
1. `\HbLib\Container\InvokerInterface`: Provides the `call` mehod.

## Usage

```php
<?php

// Helper functions for definitions:
use function \HbLib\Container\resolve;
use function \HbLib\Container\reference;
use function \HbLib\Container\factory;

interface MyInterface {}
class MyClass implements MyInterface {}
    
class MyClass2 {
    public $value;
    
    function __construct($value) {
        $this->value = $value;
    }
}
    
class MyClass3 {
    public $value;
    
    function __construct(MyInterface $value) {
        $this->value = $value;
    }
}

class MyClass4 implements MyInterface {}
class MyManager {
    function __construct(string $type) {}
    
}

$definitions = array(
    // Factories:
    'session' => factory(function() { return true; }),
    'lol' => factory(function() { return false; }),
    'hello' => factory(function() { return null; }),
    MyManager::class => factory(function() { return new MyManager('someType'); }),
    
    MyInterface::class => resolve(MyClass::class),
    
    // Providing the class to resolve is not required when you want to resolve the ID of the definition itself.
    MyClass4::class => resolve(),
    
    'differentMyInterface' => factory(function() { return new MyClass(); }),
    
    'myClass2' => resolve(MyClass::class)->parameter('value', 'someValue'),
    
    // The ->parameter part is not required as the container can resolve the parameters
    // but it is here to display that you can tell the container to use another instance.
    'myClass3' => resolve(MyClass3::class)->parameter('value', reference('differentMyInterface')),
);

// Construct the container.
$containerBuilder = new \HbLib\Container\ContainerBuilder($definitions);
$containerBuilder->enableComiling(sys_get_temp_dir() . '/CompiledContainer.php');
$container = $containerBuilder->build();

// You can also construct the container like:
// $container = new \HbLib\Container\Container(new \HbLib\Container\DefinitionSource($definitions));

// PSR-11 method:
$container->get('session'); // => true
$container->get('lol'); // => false
$container->get('hello'); // => null

// PSR-11
$container->has('session'); // => true
$container->has('where'); // => false
$container->has('hello'); // => true
```

## Compiling

The container supports compiling all resolved definitions to increase performance. The advantage of this in production environments is that the container does not need to look up all parameters everytime, but rather has all definitions and their dependencies resolved in one file.

### How to

Compiling must be enabled on ContainerBuilder and some script must call writeCompiled:

Somewhere in your web app:
```php
<?php

//...
$builder = new \HbLib\Container\ContainerBuilder(new \HbLib\Container\DefinitionSource([
    //...
]));

$filePath = sys_get_temp_dir() . '/CompiledContainer.php';
$builder->enableCompiling($filePath); // important step

if (!is_file($filePath)) {
    $builder->writeCompiled();
}

$container = $builder->build();
```

I do recommend that the `ContainerBuilder::writeCompiled` call is performed in a separate cli command so that errors
does not affect your clients.

```php
<?php
// compile_container.php

// load build just like you would in web app:
$builder = new \HbLib\Container\ContainerBuilder(new \HbLib\Container\DefinitionSource([
    //...
]));

$filePath = sys_get_temp_dir() . '/CompiledContainer.php';
$builder->enableCompiling($filePath); // important step
$builder->writeCompiled();
```
