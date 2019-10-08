<?php
namespace PoP\ComponentModel\Facades\Managers;

use PoP\ComponentModel\Managers\ModulePathManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModulePathManagerFacade
{
    public static function getInstance(): ModulePathManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('module_path_manager');
    }
}
