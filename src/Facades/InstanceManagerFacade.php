<?php
namespace PoP\ComponentModel\Facades;

use PoP\ComponentModel\Managers\InstanceManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class InstanceManagerFacade
{
    public static function getInstance(): InstanceManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('instance_manager');
    }
}
