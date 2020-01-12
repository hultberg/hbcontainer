<?php declare(strict_types=1);

namespace HbLib\Container;

abstract class AbstractDefinition
{
    public const LIFETIME_WEAK = 'weak';
    public const LIFETIME_SINGLETON = 'singleton';
    
    private string $lifetime;
    
    public function __construct()
    {
        $this->setLifetime(self::LIFETIME_WEAK);
    }
    
    public function setLifetime(string $lifetime): void
    {
        static $lifetimeConsts = [self::LIFETIME_WEAK, self::LIFETIME_SINGLETON];
        
        if (in_array($lifetime, $lifetimeConsts, true) === false) {
            throw new RuntimeException('Unknown lifetime value: ' . $lifetime);
        }
        
        $this->lifetime = $lifetime;
    }
    
    public function isSingletonLifetime(): bool
    {
        return $this->lifetime === self::LIFETIME_SINGLETON;
    }
    
    public function asSingleton(): self
    {
        $this->setLifetime(self::LIFETIME_SINGLETON);
        
        return $this;
    }
}