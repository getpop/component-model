<?php
namespace PoP\ComponentModel\Facades\Schema;

use PoP\ComponentModel\Schema\FieldQueryInterpreterInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class FieldQueryInterpreterFacade
{
    public static function getInstance(): FieldQueryInterpreterInterface
    {
        return ContainerBuilderFactory::getInstance()->get('field_query_interpreter');
    }
}
