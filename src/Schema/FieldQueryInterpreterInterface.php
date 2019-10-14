<?php
namespace PoP\ComponentModel\Schema;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface FieldQueryInterpreterInterface
{
    public function getFieldName(string $field): string;
    public function getFieldArgs(string $field): ?string;
    public function isSkipOuputIfNull(string $field): bool;
    public function getFieldAlias(string $field): ?string;
    public function getFieldDirectives(string $field): ?string;
    public function getDirectives(string $field): array;
    public function extractFieldDirectives(string $fieldDirectives): array;
    public function composeFieldDirectives(array $fieldDirectives): string;
    public function convertDirectiveToFieldDirective(array $fieldDirective): string;
    public function listFieldDirective(string $fieldDirective): array;
    public function getFieldDirectiveName(string $fieldDirective): string;
    public function getFieldDirectiveArgs(string $fieldDirective): ?string;
    public function getFieldDirective(string $directiveName, array $directiveArgs = []): string;
    public function getDirectiveName(array $directive): string;
    public function getDirectiveArgs(array $directive): ?string;
    public function getFieldOutputKey(string $field): string;
    public function listField(string $field): array;
    public function getField(string $fieldName, array $fieldArgs = [], ?string $fieldAlias = null, ?bool $skipOutputIfNull = false, ?array $fieldDirectives = []): string;
    public function composeField(string $fieldName, string $fieldArgs = '', string $fieldAlias = '', string $skipOutputIfNull = '', string $fieldDirectives = ''): string;
    public function getFieldDirectiveAsString(array $fieldDirectives): string;
    public function isFieldArgumentValueAField($fieldArgValue): bool;
    public function extractFieldArguments(FieldResolverInterface $fieldResolver, string $field, ?array &$schemaWarnings = null): array;
    public function extractFieldArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array;
    public function extractFieldArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array;
    public function extractDirectiveArgumentsForResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $field, ?array $variables = null): array;
    public function extractDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, string $field, ?array $variables = null): array;
    public function getAsFieldArgValueField(string $fieldName): string;
    public function maybeConvertFieldArgumentArrayValueFromStringToArray(string $fieldArgValue);
}
