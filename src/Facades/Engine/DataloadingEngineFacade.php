<?php
namespace PoP\ComponentModel\Facades\Engine;

use PoP\ComponentModel\Engine\DataloadingEngineInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class DataloadingEngineFacade
{
    public static function getInstance(): DataloadingEngineInterface
    {
        return ContainerBuilderFactory::getInstance()->get('dataloading_engine');
    }
}
