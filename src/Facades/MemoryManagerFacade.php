<?php
namespace PoP\ComponentModel\Facades;

use PoP\ComponentModel\Cache\CacheInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class MemoryManagerFacade
{
    public static function getInstance(): CacheInterface
    {
        return ContainerBuilderFactory::getInstance()->get('memory_cache');
    }
}
