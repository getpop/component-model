<?php
namespace PoP\ComponentModel\Facades;

use PoP\ComponentModel\Info\ApplicationInfoInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ApplicationInfoFacade
{
    public static function getInstance(): ApplicationInfoInterface
    {
        return ContainerBuilderFactory::getInstance()->get('application_info');
    }
}
