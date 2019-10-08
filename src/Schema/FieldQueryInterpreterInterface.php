<?php
namespace PoP\ComponentModel\Schema;

interface FieldQueryInterpreterInterface
{
    public function getFieldName(string $field): string;
    public function getFieldArgs(string $field): ?string;
    public function extractFieldArgumentsForResultItem($fieldResolver, $resultItem, string $field, ?array $variables = null): array;
    public function extractFieldArgumentsForSchema($fieldResolver, string $field, ?array $variables = null): array;
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
    public function getField(string $fieldName, array $fieldArgs = [], string $fieldAlias = null, array $fieldDirectives = []): string;
    public function getFieldDirectiveAsString(array $fieldDirectives): string;
}
