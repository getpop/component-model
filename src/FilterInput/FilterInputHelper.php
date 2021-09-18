<?php

declare(strict_types=1);

namespace PoP\ComponentModel\FilterInput;

use PoP\ComponentModel\Facades\FilterInputProcessors\FilterInputProcessorManagerFacade;
use PoP\ComponentModel\Facades\ModuleProcessors\ModuleProcessorManagerFacade;
use PoP\ComponentModel\ModuleProcessors\FormComponentModuleProcessorInterface;

class FilterInputHelper
{
    public static function getFilterInputName(array $filterInputModule): string
    {
        $moduleProcessorManager = ModuleProcessorManagerFacade::getInstance();
        /** @var FormComponentModuleProcessorInterface */
        $filterInputModuleProcessor = $moduleProcessorManager->getProcessor($filterInputModule);
        return $filterInputModuleProcessor->getName($filterInputModule);
    }
}
