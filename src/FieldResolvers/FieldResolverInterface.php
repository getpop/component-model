<?php
namespace PoP\ComponentModel\FieldResolvers;

interface FieldResolverInterface
{
    public function getId($resultItem);
    public function getIdFieldDataloaderClass();
    public function getFieldNamesToResolve(): array;
    public function getDirectiveNameClasses(): array;
    public function addDataitemsToHeap(array $ids_data_fields, array &$resultIDItems);
    public function addDataitems(array $ids_data_fields, array &$resultIDItems, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
    public function resolveSchemaValidationErrorDescriptions(string $field): ?array;
    public function getFieldDocumentationWarningDescriptions(string $field): ?array;
    public function getFieldDocumentationDeprecationDescriptions(string $field): ?array;
    public function getFieldDocumentationArgs(string $field): array;
    public function enableOrderedFieldDocumentationArgs(string $field): bool;
    public function resolveFieldDefaultDataloaderClass(string $field): ?string;
    public function resolveValue($resultItem, string $field);
    public function getSchemaDocumentation(array $fieldArgs = [], array $options = []): array;
    public function hasFieldValueResolversForField(string $field): bool;
}
