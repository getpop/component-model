<?php
namespace PoP\ComponentModel\Facades\Engine;

use PoP\ComponentModel\Engine\EngineInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class EngineFacade
{
    public static function getInstance(): EngineInterface
    {
        return ContainerBuilderFactory::getInstance()->get('engine');
    }
}
