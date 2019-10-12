<?php
namespace PoP\ComponentModel\Facades\Managers;

use PoP\ComponentModel\Managers\ModuleProcessorManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModuleProcessorManagerFacade
{
    public static function getInstance(): ModuleProcessorManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('module_processor_manager');
    }
}
