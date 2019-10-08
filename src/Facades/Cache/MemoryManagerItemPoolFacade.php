<?php
namespace PoP\ComponentModel\Facades\Cache;

use Psr\Cache\CacheItemPoolInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class MemoryManagerItemPoolFacade
{
    public static function getInstance(): CacheItemPoolInterface
    {
        return ContainerBuilderFactory::getInstance()->get('memory_cache_item_pool');
    }
}
