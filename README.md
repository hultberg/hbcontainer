# HbContainer

[![Build Status](https://travis-ci.org/hultberg/hbcontainer.svg?branch=master)](https://travis-ci.org/hultberg/hbcontainer)

A lightweight dependency injection container for all types of projects. Supports php 7.1 and newer.

## Installation

With composer:
```
composer require edvin/hbcontainer
```

## Usage

```php
<?php
// Create definition configs

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

$definitions = array(
    'session' => factory(function() { return true; }),
    'lol' => factory(function() { return false; }),
    'hello' => factory(function() { return null; }),
    MyInterface::class => get(MyClass::class),
    'differentMyInterface' => factory(function() { return new MyClass(); }),
    'myClass2' => get(MyClass::class)->parameter('value', 'someValue'),
    
    // The ->parameter part is not required as the container can resolve the parameters
    // but it is here to display that you can tell the container to use another instance.
    'myClass3' => get(MyClass3::class)->parameter('value', get('differentMyInterface')),
);

// Construct the container.
$container = new \HbLib\Container\Container($definitions);

// PSR-11 method:
$container->get('session'); // => true
$container->get('lol'); // => false
$container->get('hello'); // => null

// PSR-11
$container->has('session'); // => true
$container->has('where'); // => false
$container->has('hello'); // => true
```

