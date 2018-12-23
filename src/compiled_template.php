<?php
/**
 * @var string $compileParentClass
 * @var string $compileClass
 * @var \HbLib\Container\Compiler $this
 */
?>

final class <?php echo ltrim($this->compiledClassName, '\\'); ?> extends <?php echo $this->compiledParentClassName; ?>
{
    protected function _initialize(): void {
        parent::_initialize();

        $this->methodMapping = new \Ds\Map(<?php var_export($this->entryToMethods->toArray()); ?>);
    }

<?php foreach ($this->methods as $method): ?>
    protected function <?php echo $method->getName(); ?>()
    {
        <?php echo $method->getContent(); ?>

    }

<?php endforeach; ?>

}
