<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\DataloaderInterface;

interface FieldResolverInterface
{
    public function getId($resultItem);
    public function getIdFieldDataloaderClass();
    public function getFieldNamesToResolve(): array;
    public function getDirectiveNameClasses(): array;
    public function validateFieldArgumentsForSchema(string $field, array $fieldArgs, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;
    public function enqueueFillingResultItemsFromIDs(array $ids_data_fields, array &$resultIDItems);
    public function fillResultItems(DataloaderInterface $dataloader, array $ids_data_fields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages);
    public function resolveSchemaValidationErrorDescriptions(string $field, array &$variables = null): ?array;
    public function resolveSchemaValidationWarningDescriptions(string $field, array &$variables = null): ?array;
    public function getSchemaDeprecationDescriptions(string $field, array &$variables = null): ?array;
    public function getSchemaFieldArgs(string $field): array;
    public function enableOrderedSchemaFieldArgs(string $field): bool;
    public function resolveFieldDefaultDataloaderClass(string $field): ?string;
    public function resolveValue($resultItem, string $field, ?array $variables = null);
    public function getSchemaDefinition(array $fieldArgs = [], array $options = []): array;
    public function hasFieldValueResolversForField(string $field): bool;
}
