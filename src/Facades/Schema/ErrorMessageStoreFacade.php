<?php
namespace PoP\ComponentModel\Facades\Schema;

use PoP\ComponentModel\Schema\ErrorMessageStoreInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class ErrorMessageStoreFacade
{
    public static function getInstance(): ErrorMessageStoreInterface
    {
        return ContainerBuilderFactory::getInstance()->get('error_message_store');
    }
}
