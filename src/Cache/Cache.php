<?php
namespace PoP\ComponentModel\Cache;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Cache\CacheItemInterface;
use PoP\ComponentModel\ModelInstance\ModelInstanceInterface;

class Cache implements CacheInterface
{
    use ReplaceCurrentExecutionDataWithPlaceholdersTrait;
    protected $cacheItemPool;
    protected $modelInstance;

    public function __construct(
        CacheItemPoolInterface $cacheItemPool,
        ModelInstanceInterface $modelInstance
    ) {
        $this->cacheItemPool = $cacheItemPool;
        $this->modelInstance = $modelInstance;
    }

    protected function getKey($id, $type)
    {
        return $type . '.' . $id;
    }

    protected function getCacheItem($id, $type): CacheItemInterface
    {
        return $this->cacheItemPool->getItem($this->getKey($id, $type));
    }

    public function hasCache($id, $type)
    {
        $cacheItem = $this->getCacheItem($id, $type);
        return $cacheItem->isHit();
    }

    public function getCache($id, $type)
    {
        $cacheItem = $this->getCacheItem($id, $type);
        if ($cacheItem->isHit()) {

            // Return the file contents
            $content = $cacheItem->get();

            // Inject the current request data in place of the placeholders (pun not intended!)
            return $this->replacePlaceholdersWithCurrentExecutionData($content);
        }

        return false;
    }

    /**
     * Store the cache
     *
     * @param [type] $id key under which to store the cache
     * @param [type] $type the type of the cache, used to distinguish groups of caches
     * @param [type] $content the value to cache
     * @param [type] $time time after which the cache expires, in seconds
     * @return void
     */
    public function storeCache($id, $type, $content, $time = null)
    {
        // Before saving the cache, replace the data specific to this execution with generic placeholders
        $content = $this->replaceCurrentExecutionDataWithPlaceholders($content);
        $cacheItem = $this->getCacheItem($id, $type);
        $cacheItem->set($content);
        $cacheItem->expiresAfter($time);
        $this->saveCache($cacheItem);
    }

    /**
     * Save immediately. Can override to save as deferred
     *
     * @param CacheItemInterface $cacheItem
     * @return void
     */
    protected function saveCache(CacheItemInterface $cacheItem)
    {
        $this->cacheItemPool->save($cacheItem);
    }

    public function getCacheByModelInstance($type)
    {
        return $this->getCache($this->modelInstance->getModelInstanceId(), $type);
    }

    public function storeCacheByModelInstance($type, $content)
    {
        return $this->storeCache($this->modelInstance->getModelInstanceId(), $type, $content);
    }
}
