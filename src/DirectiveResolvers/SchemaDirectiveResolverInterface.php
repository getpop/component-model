<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface SchemaDirectiveResolverInterface
{
    public function getSchemaFieldDescription(FieldResolverInterface $fieldResolver): ?string;
    public function getSchemaFieldDeprecationDescription(FieldResolverInterface $fieldResolver): ?string;
    public function getSchemaFieldArgs(FieldResolverInterface $fieldResolver): array;
}
