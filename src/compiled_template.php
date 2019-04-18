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

        $this->methodMapping = <?php var_export($this->entryToMethods); ?>;
    }

<?php foreach ($this->methods as $method): ?>
    protected function <?php echo $method->getName(); ?>()
    {
        <?php echo $method->getContent(); ?>

    }

<?php endforeach; ?>

}
