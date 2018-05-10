# Changelog

## 1.5.0

* Bug: Fix container not resolving parameters without a specific type that has default values.
* Allow to declare parameters with the `get()` definition helper.
* Internal refactoring and code splitting.
* Add interface `ArgumentResolverInterface` for the method `resolveArguments()`

## 1.4.0

* Internal resolving of dependencies now call `get()` and will then use singletons.
* Added function to create a defintion factory: `factory()`
* Added function to create an alias to resolve a function: `getClass()`

## 1.3.0

* Change method usages for `get` and `make`. `make` will now always provide a new instance, `get` will always provide a singleton instance.
* Added `FactoryInterface`
* Project now has 100% test coverage and is verified to work on php 7.1 and 7.2

## 1.2.0

* Make `Container` resolvable.
* Make `Container::resolveParameters` public.
* Support anonymous functions in the Container.

## 1.1.0

* Implement `Container::call()`
* Added an base Exception class: `ContainerException`
* Use Relflection API when invoking definition factories.

## 1.0.1

* fix: Added missing class `UnresolvedContainerException`

## 1.0.0

* Initial release.
