<?php
/**
 * @var string $compileParentClass
 * @var string $compileClass
 * @var \HbLib\Container\Compiler $this
 */
?>

final class <?=ltrim($compiledClassName, '\\')?> extends <?=$this->compiledParentClassName?>
{
    protected function _initialize(): void {
        parent::_initialize();

        $this->methodMapping = <?=var_export($this->entryToMethods, true)?>;
    }

<?php foreach ($this->methods as $method): ?>
    protected function <?=$method->getName()?>(): <?=$method->getReturnType()?>
    {
        <?=$method->getContent()?>

    }

<?php endforeach; ?>

}

return <?=$compiledClassName?>::class;
