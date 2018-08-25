# Changelog

## v2.1.1

* Fix unable to compile any function that had interface type-hinted in its arguments.

## v2.1.0

* Add definition `value`, it can hold any value you provide it.
* Change definition `reference` to reference any definition entry, not just classes.

## v2.0.0

* Implement compiling container with all defined definitions
* Rename helper function `\HbLib\Container\get` to `\HbLib\Container\resolve`
* Add new definition `Reference`, use it to reference other definitions. A helper function has been added for it: `\HbLib\Container\reference`
* Add new `DefinitionSource` object to hold all definitions.
* Add new `ContainerBuilder`. It allows you to enable compiling.
* Implement circular dependency resolving checking

## 1.6.3

* Add method `Container::set()`, it is not part of any interface.

## 1.6.2

* Fix resolving builtin types with default values.
* Refactor the logic for resolving parameters

## 1.6.1

`Container::has()` will no longer call `make()`. This is not seen as a breaking change because the `has` method is documented to not always predict if `get` can return something.

## 1.6.0

* Bug: Argument resolver did not resolve Definitions
* `DefinitionClass` now does not require a class name. When not specified, the container will find the class being resolved with the definition and use it. This allows us to write (in definition array): `MyClass::class => get()` and not `MyClass::class => get(MyClass::class)`
* Add interface `InvokerInterface` for method `call` 
* Add interface `ArgumentResolverInterface` for the method `resolveArguments()`
* Internal refactoring and code splitting.

## 1.5.0

* Bug: Fix container not resolving parameters without a specific type that has default values.
* Allow to declare parameters with the `get()` definition helper.

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
