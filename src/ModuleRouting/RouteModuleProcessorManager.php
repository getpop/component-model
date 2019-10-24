<?php
namespace PoP\ComponentModel\ModuleRouting;

use PoP\ModuleRouting\AbstractRouteModuleProcessorManager;
use PoP\ComponentModel\Engine_Vars;

class RouteModuleProcessorManager extends AbstractRouteModuleProcessorManager
{
    public function getVars(): array
    {
        return Engine_Vars::getVars();
    }
}
