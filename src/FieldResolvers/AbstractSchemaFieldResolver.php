<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\FieldResolvers\SelfSchemaDefinitionResolverTrait;
use PoP\ComponentModel\FieldResolvers\FieldSchemaDefinitionResolverInterface;

abstract class AbstractSchemaFieldResolver extends AbstractFieldResolver implements FieldSchemaDefinitionResolverInterface
{
    use SelfSchemaDefinitionResolverTrait;
}
