# Changelog

## 1.3.0

* Change method usages for `get` and `make`. `make` will now always provide a new instance, `get` will always provide a singleton instance.
* Added `FactoryInterface`

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
