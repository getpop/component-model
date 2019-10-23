<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface FieldQueryInterpreterInterface extends \PoP\FieldQuery\FieldQueryInterpreterInterface
{
    public function extractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null, ?array &$schemaWarnings = null): array;
    public function extractFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array;
    public function extractDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array;
    public function extractFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array;
    public function extractDirectiveArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array;
}
