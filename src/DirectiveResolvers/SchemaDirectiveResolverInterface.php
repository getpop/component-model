<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface SchemaDirectiveResolverInterface
{
    /**
     * Description of the directive, to be output as documentation in the schema
     *
     * @param FieldResolverInterface $fieldResolver
     * @return string|null
     */
    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string;
    /**
     * Indicates if the directive argument names can be omitted from the query, deducing them from the order in which they were defined in the schema
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $directive
     * @return boolean
     */
    public function enableOrderedSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): bool;
    /**
     * Schema Directive Arguments
     *
     * @param FieldResolverInterface $fieldResolver
     * @return array
     */
    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array;
    /**
     * Expressions set by the directive
     *
     * @param FieldResolverInterface $fieldResolver
     * @return string|null
     */
    public function getSchemaDirectiveExpressions(FieldResolverInterface $fieldResolver): array;
    /**
     * Indicate if the directive has been deprecated, why, when, and/or how it must be replaced
     *
     * @param FieldResolverInterface $fieldResolver
     * @return string|null
     */
    public function getSchemaDirectiveDeprecationDescription(FieldResolverInterface $fieldResolver): ?string;
    /**
     * Indicate if the directive is global (i.e. it can be applied to all fields, for all fieldResolvers)
     *
     * @param FieldResolverInterface $fieldResolver
     * @return bool
     */
    public function isGlobal(FieldResolverInterface $fieldResolver): bool;

}
