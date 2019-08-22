<?php
namespace PoP\ComponentModel\Facades;

use PoP\ComponentModel\Cache\CacheInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class RequestCacheFacade
{
    public static function getInstance(): CacheInterface
    {
        return ContainerBuilderFactory::getInstance()->get('request_cache');
    }
}
