<?php
namespace PoP\ComponentModel\Facades;

use Psr\Cache\CacheItemPoolInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class PersistentCacheItemPoolFacade
{
    public static function getInstance(): CacheItemPoolInterface
    {
        return ContainerBuilderFactory::getInstance()->get('persistent_cache_item_pool');
    }
}
