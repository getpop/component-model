<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Facades\ModuleFiltering;

use PoP\ComponentModel\ModuleFiltering\ModuleFilterManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModuleFilterManagerFacade
{
    public static function getInstance(): ModuleFilterManagerInterface
    {
        /**
         * @var ModuleFilterManagerInterface
         */
        $service = ContainerBuilderFactory::getInstance()->get('module_filter_manager');
        return $service;
    }
}
