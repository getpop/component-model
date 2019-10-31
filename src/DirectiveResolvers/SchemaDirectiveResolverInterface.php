<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface SchemaDirectiveResolverInterface
{
    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string;
    public function getSchemaDirectiveDeprecationDescription(FieldResolverInterface $fieldResolver): ?string;
    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array;
}
