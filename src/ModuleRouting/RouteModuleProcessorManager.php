<?php
namespace PoP\ComponentModel\ModuleRouting;

use PoP\ModuleRouting\AbstractRouteModuleProcessorManager;
use PoP\ComponentModel\State\ApplicationState;

class RouteModuleProcessorManager extends AbstractRouteModuleProcessorManager
{
    public function getVars(): array
    {
        return ApplicationState::getVars();
    }
}
