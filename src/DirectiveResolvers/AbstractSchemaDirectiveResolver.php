<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

abstract class AbstractSchemaDirectiveResolver extends AbstractDirectiveResolver implements SchemaDirectiveResolverInterface
{
    public function getSchemaDefinitionResolver(FieldResolverInterface $fieldResolver): ?SchemaDirectiveResolverInterface
    {
        return $this;
    }
    public function getSchemaFieldDescription(FieldResolverInterface $fieldResolver): ?string
    {
        return null;
    }
    public function getSchemaFieldDeprecationDescription(FieldResolverInterface $fieldResolver): ?string
    {
        return null;
    }
    public function getSchemaFieldArgs(FieldResolverInterface $fieldResolver): array
    {
        return [];
    }
}
