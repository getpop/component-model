<?php
namespace PoP\ComponentModel\ModuleRouting;

use PoP\ModuleRouting\AbstractRouteModuleProcessorManager;

class RouteModuleProcessorManager extends AbstractRouteModuleProcessorManager
{
    public function getVars()
    {
        return Engine_Vars::getVars();
    }
}
