<?php
namespace PoP\ComponentModel\Cache;
use Psr\Cache\CacheItemPoolInterface;
use PoP\Hooks\Contracts\HooksAPIInterface;
use PoP\ComponentModel\ModelInstance\ModelInstanceInterface;

class Cache implements CacheInterface
{
    use ReplaceCurrentExecutionDataWithPlaceholdersTrait;
    protected $cacheItemPool;
    protected $hooksAPI;
    protected $modelInstance;

    public function __construct(
        CacheItemPoolInterface $cacheItemPool,
        HooksAPIInterface $hooksAPI,
        ModelInstanceInterface $modelInstance
    ) {
        $this->cacheItemPool = $cacheItemPool;
        $this->hooksAPI = $hooksAPI;
        $this->modelInstance = $modelInstance;

        // When a plugin is activated/deactivated, ANY plugin, delete the corresponding cached files
        // This is particularly important for the MEMORY, since we can't set by constants to not use it
        $this->hooksAPI->addAction(
            'popcms:componentInstalledOrUninstalled',
            function () {
                $this->cacheItemPool->clear();
            }
        );

        // Save all deferred cacheItems
        $this->hooksAPI->addAction(
            'popcms:shutdown',
            function () {
                $this->cacheItemPool->commit();
            }
        );
    }

    protected function getKey($id, $type)
    {
        return $type . '.' . $id;
    }

    protected function getCacheItem($id, $type)
    {
        return $this->cacheItemPool->getItem($this->getKey($id, $type));
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

    public function storeCache($id, $type, $content)
    {
        // Before saving the cache, replace the data specific to this execution with generic placeholders
        $content = $this->replaceCurrentExecutionDataWithPlaceholders($content);

        $cacheItem = $this->getCacheItem($id, $type);
        $cacheItem->set($content);
        $this->cacheItemPool->saveDeferred($cacheItem);
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
