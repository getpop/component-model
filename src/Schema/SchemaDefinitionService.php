<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Environment;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\FieldResolvers\FieldInterfaceResolverInterface;

class SchemaDefinitionService implements SchemaDefinitionServiceInterface
{
    public function getInterfaceSchemaKey(FieldInterfaceResolverInterface $interfaceResolver, array $options = []): string
    {
        // By default, use the type name
        return $interfaceResolver->getMaybeNamespacedInterfaceName();
    }
    public function getTypeSchemaKey(TypeResolverInterface $typeResolver, array $options = []): string
    {
        // By default, use the type name
        return $typeResolver->getMaybeNamespacedTypeName();
    }
}
