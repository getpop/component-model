<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Facades\ModuleFilters;

use PoP\ComponentModel\ModuleFilters\ModuleFilterManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModuleFilterManagerFacade
{
    public static function getInstance(): ModuleFilterManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('module_filter_manager');
    }
}
