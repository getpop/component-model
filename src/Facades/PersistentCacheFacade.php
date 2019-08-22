<?php
namespace PoP\ComponentModel\Facades;

use PoP\ComponentModel\Cache\CacheInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class PersistentCacheFacade
{
    public static function getInstance(): ?CacheInterface
    {
        $containerBuilderFactory = ContainerBuilderFactory::getInstance();
        if ($containerBuilderFactory->has('persistent_cache')) {
            return $containerBuilderFactory->get('persistent_cache');
        }
        return null;
    }
}
