# HbContainer

[![Build Status](https://travis-ci.org/hultberg/hbcontainer.svg?branch=master)](https://travis-ci.org/hultberg/hbcontainer)

A lightweight container.

## Usage

```php
<?php
// Create definition configs
$definitions = array(
    'session' => function() { return true; },
    'lol' => function() { return false; },
    'hello' => function() { return null; },
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

