<?php
namespace PoP\ComponentModel\Facades\Managers;

use PoP\ComponentModel\ModuleFilters\ModuleFilterManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModuleFilterManagerFacade
{
    public static function getInstance(): ModuleFilterManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('module_filter_manager');
    }
}
