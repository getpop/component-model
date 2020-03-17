<?php
namespace PoP\ComponentModel\Facades\Registries;

use PoP\ComponentModel\Registries\DirectiveRegistryInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class DirectiveRegistryFacade
{
    public static function getInstance(): DirectiveRegistryInterface
    {
        return ContainerBuilderFactory::getInstance()->get('directive_registry');
    }
}
