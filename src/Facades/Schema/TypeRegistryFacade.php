<?php
namespace PoP\ComponentModel\Facades\Schema;

use PoP\ComponentModel\Schema\TypeRegistryInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class TypeRegistryFacade
{
    public static function getInstance(): TypeRegistryInterface
    {
        return ContainerBuilderFactory::getInstance()->get('type_registry');
    }
}
