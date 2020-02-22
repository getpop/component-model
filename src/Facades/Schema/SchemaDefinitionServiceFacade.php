<?php
namespace PoP\ComponentModel\Facades\Schema;

use PoP\ComponentModel\Schema\SchemaDefinitionServiceInterface;
use PoP\Root\Container\ContainerBuilderFactory;

class SchemaDefinitionServiceFacade
{
    public static function getInstance(): SchemaDefinitionServiceInterface
    {
        return ContainerBuilderFactory::getInstance()->get('schema_definition_service');
    }
}