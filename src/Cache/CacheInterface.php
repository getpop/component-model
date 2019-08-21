<?php
namespace PoP\ComponentModel\Cache;

interface CacheInterface
{
    public function getCache($id, $type);
    public function storeCache($id, $type, $content);
    public function getCacheByModelInstance($type);
    public function storeCacheByModelInstance($type, $content);
}
