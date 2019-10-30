<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface FieldQueryInterpreterInterface extends \PoP\FieldQuery\FieldQueryInterpreterInterface
{
    /**
     * Extract field args without using the schema. It is needed to find out which fieldValueResolver will process a field, where we can't depend on the schema since this one needs to know who the fieldValueResolver is, creating an infitine loop
     *
     * @param string $field
     * @param array|null $variables
     * @return array
     */
    public function extractStaticFieldArguments(string $field, ?array $variables = null): array;
    public function extractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null, ?array &$schemaWarnings = null): array;
    public function extractFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array;
    public function extractDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array;
    public function extractFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array;
    public function extractDirectiveArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array;
}
