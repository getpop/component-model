<?php
namespace PoP\ComponentModel\Cache;

interface CacheInterface
{
    public function hasCache($id, $type);
    public function getCache($id, $type);
    public function getComponentModelCache($id, $type);

    /**
     * Store the cache
     *
     * @param [type] $id key under which to store the cache
     * @param [type] $type the type of the cache, used to distinguish groups of caches
     * @param [type] $content the value to cache
     * @param [type] $time time after which the cache expires, in seconds
     * @return void
     */
    public function storeCache($id, $type, $content, $time = null);
    public function storeComponentModelCache($id, $type, $content, $time = null);
    public function getCacheByModelInstance($type);
    public function storeCacheByModelInstance($type, $content);
}
