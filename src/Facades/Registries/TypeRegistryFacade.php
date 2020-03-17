<?php
namespace PoP\ComponentModel\Facades\Registries;

use PoP\ComponentModel\Registries\TypeRegistryInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class TypeRegistryFacade
{
    public static function getInstance(): TypeRegistryInterface
    {
        return ContainerBuilderFactory::getInstance()->get('type_registry');
    }
}
