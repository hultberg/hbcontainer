<?php
/**
 * @var string $compileParentClass
 * @var string $compileClass
 * @var \HbLib\Container\Compiler $this
 */
?>

class <?php echo ltrim($this->compiledClassName, '\\'); ?> extends <?php echo $this->compiledParentClassName; ?> 
{
    const METHOD_MAPPING = <?php var_export($this->entryToMethods); ?>;
    
<?php foreach ($this->methods as $methodName => $content): ?>
    protected function <?php echo $methodName; ?>()
    {
        <?php echo $content; ?>
        
    }
    
<?php endforeach; ?>

}