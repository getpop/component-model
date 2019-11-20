<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\DirectivePipeline\DirectivePipelineDecorator;

interface FieldResolverInterface
{
    public function getId($resultItem);
    public function getIdFieldDataloaderClass();
    public function getFieldNamesToResolve(): array;
    public function getDirectiveNameClasses(): array;
    public function validateFieldArgumentsForSchema(string $field, array $fieldArgs, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;
    public function enqueueFillingResultItemsFromIDs(array $ids_data_fields, /*array &$resultIDItems, */bool $isRootDirective);
    public function fillResultItems(DataloaderInterface $dataloader, array $ids_data_fields, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
    public function resolveSchemaValidationErrorDescriptions(string $field, array &$variables = null): ?array;
    public function resolveSchemaValidationWarningDescriptions(string $field, array &$variables = null): ?array;
    public function getSchemaDeprecationDescriptions(string $field, array &$variables = null): ?array;
    public function getSchemaFieldArgs(string $field): array;
    public function enableOrderedSchemaFieldArgs(string $field): bool;
    public function resolveFieldDefaultDataloaderClass(string $field): ?string;
    public function resolveValue($resultItem, string $field, ?array $variables = null, ?array $expressions = null, array $options = []);
    public function getSchemaDefinition(array $fieldArgs = [], array $options = []): array;
    public function hasFieldValueResolversForField(string $field): bool;
    // public function getFieldDirectivePipeline(array $fieldDirectives, array &$fieldDirectiveFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): DirectivePipelineDecorator;
    // public function getDirectiveNestedDirectivePipeline(array $fieldDirectives, array &$fieldDirectiveFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): DirectivePipelineDecorator;
    public function getDirectivePipelineData(array $fieldDirectives, array &$fieldDirectiveFields, /*bool $isRootDirective, */array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;
    public function getDirectivePipeline(array $orderedDirectiveInstances): DirectivePipelineDecorator;
}
