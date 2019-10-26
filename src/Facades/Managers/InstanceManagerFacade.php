<?php
namespace PoP\ComponentModel\Facades\Managers;

use PoP\ComponentModel\Instances\InstanceManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class InstanceManagerFacade
{
    public static function getInstance(): InstanceManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('instance_manager');
    }
}
