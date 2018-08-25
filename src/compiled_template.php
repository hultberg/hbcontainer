class <?php echo $compileClass; ?> extends <?php echo $compileParentClass; ?> 
{
    const METHOD_MAPPING = <?php var_export($this->entryToMethods); ?>;
    
<?php foreach ($this->methods as $methodName => $content): ?>
    protected function <?php echo $methodName; ?>()
    {
        <?php echo $content; ?>
        
    }
    
<?php endforeach; ?>

}