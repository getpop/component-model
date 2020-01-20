<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\FieldResolvers\FieldInterfaceResolverInterface;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

interface SchemaDefinitionServiceInterface
{
    public function getInterfaceSchemaKey(FieldInterfaceResolverInterface $interfaceResolver, array $options = []): string;
    public function getTypeSchemaKey(TypeResolverInterface $typeResolver, array $options = []): string;
}
