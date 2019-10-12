<?php
namespace PoP\ComponentModel\Managers;

interface ItemProcessorManagerInterface
{
    public function getLoadedItemFullNameProcessorInstances();
    public function getLoadedItems();
    public function overrideProcessorClass(string $overrideClass, string $withClass, array $forItemNames): void;
    public function getItemProcessor(array $item);
    public function getProcessor(array $item);
}
