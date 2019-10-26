<?php
namespace PoP\ComponentModel\Facades\ModuleProcessors;

use PoP\ComponentModel\ModuleProcessors\ModuleProcessorManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModuleProcessorManagerFacade
{
    public static function getInstance(): ModuleProcessorManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('module_processor_manager');
    }
}
