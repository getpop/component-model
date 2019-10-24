<?php
namespace PoP\ComponentModel\ModuleRouting;

use PoP\ModuleRouting\AbstractRouteModuleProcessorManager;

class RouteModuleProcessorManager extends AbstractRouteModuleProcessorManager
{
    public function getVars(): array
    {
        return Engine_Vars::getVars();
    }
}
