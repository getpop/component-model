<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface SchemaDirectiveResolverInterface
{
    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string;
    public function getSchemaDirectiveDeprecationDescription(FieldResolverInterface $fieldResolver): ?string;
    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array;
    /**
     * Indicates if the directive argument names can be omitted from the query, deducing them from the order in which they were defined in the schema
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $directive
     * @return boolean
     */
    public function enableOrderedSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): bool;

}
