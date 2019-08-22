<?php
namespace PoP\ComponentModel\Facades;

use Psr\Cache\CacheItemPoolInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class RequestCacheItemPoolFacade
{
    public static function getInstance(): ?CacheItemPoolInterface
    {
        $containerBuilderFactory = ContainerBuilderFactory::getInstance();
        if ($containerBuilderFactory->has('request_cache_item_pool')) {
            return $containerBuilderFactory->get('request_cache_item_pool');
        }
        return null;
    }
}
