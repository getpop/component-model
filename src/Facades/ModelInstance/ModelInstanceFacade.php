<?php
namespace PoP\ComponentModel\Facades\ModelInstance;

use PoP\ComponentModel\ModelInstance\ModelInstanceInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ModelInstanceFacade
{
    public static function getInstance(): ModelInstanceInterface
    {
        return ContainerBuilderFactory::getInstance()->get('model_instance');
    }
}
