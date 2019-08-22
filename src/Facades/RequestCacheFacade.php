<?php
namespace PoP\ComponentModel\Facades;

use PoP\ComponentModel\Cache\CacheInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class RequestCacheFacade
{
    public static function getInstance(): ?CacheInterface
    {
        $containerBuilderFactory = ContainerBuilderFactory::getInstance();
        if ($containerBuilderFactory->has('request_cache')) {
            return $containerBuilderFactory->get('request_cache');
        }
        return null;
    }
}
