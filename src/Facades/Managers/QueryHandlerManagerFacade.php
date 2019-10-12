<?php
namespace PoP\ComponentModel\Facades\Managers;

use PoP\ComponentModel\Managers\QueryHandlerManagerInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class QueryHandlerManagerFacade
{
    public static function getInstance(): QueryHandlerManagerInterface
    {
        return ContainerBuilderFactory::getInstance()->get('query_handler_manager');
    }
}
