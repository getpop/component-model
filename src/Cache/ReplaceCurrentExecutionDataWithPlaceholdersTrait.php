<?php
namespace PoP\ComponentModel\Cache;

trait ReplaceCurrentExecutionDataWithPlaceholdersTrait
{
    protected function getCacheReplacements()
    {
        return [
            POP_CONSTANT_UNIQUE_ID => POP_CACHEPLACEHOLDER_UNIQUE_ID,
            POP_CONSTANT_CURRENTTIMESTAMP => POP_CACHEPLACEHOLDER_CURRENTTIMESTAMP,
            POP_CONSTANT_RAND => POP_CACHEPLACEHOLDER_RAND,
            POP_CONSTANT_TIME => POP_CACHEPLACEHOLDER_TIME,
        ];
    }

    protected function replaceCurrentExecutionDataWithPlaceholders($content)
    {
        $replacements = $this->getCacheReplacements();
        return str_replace(
            array_keys($replacements), 
            array_values($replacements), 
            $content
        );
    }

    protected function replacePlaceholdersWithCurrentExecutionData($content)
    {
        // Replace the placeholder for the uniqueId with the current uniqueId
        // Do the same with all dynamic constants, so that we can generate a proper ETag also when retrieving the cached value
        $replacements = $this->getCacheReplacements();
        return str_replace(
            array_values($replacements), 
            array_keys($replacements), 
            $content
        );
    }
}
