<?php
declare(strict_types=1);

require __DIR__ . '/vendor/autoload.php';

class Cat {}
class Dog {
    public function __construct(
        private Cat $cat,
    ) {}
}
interface Foo {}
class Bla implements Foo {}

$definitions = new \HbLib\Container\DefinitionSource([
    Cat::class => \HbLib\Container\factory(static
    function (): Cat
    {
        return new Cat();
    }),
    Dog::class => \HbLib\Container\factory(static function (Cat $cat): Dog {
        return new Dog($cat);
    }),
    Foo::class => \HbLib\Container\reference(Bla::class),
    Bla::class => \HbLib\Container\resolve(),
]);

$compiler = new \HbLib\Container\Compiler();
echo $compiler->compile($definitions);
