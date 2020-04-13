<?php

declare(strict_types=1);

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
