<?php
namespace PoP\ComponentModel\FieldValueResolvers;

interface FieldValueResolverInterface
{
    /**
     * Get an array with the fieldNames that this fieldValueResolver resolves
     *
     * @return array
     */
    public function getFieldNamesToResolve(): array;

    /**
     * Indicates if the fieldValueResolver can process this combination of fieldName and fieldArgs
     * It is required to support a multiverse of fields: different fieldValueResolvers can resolve the field, based on the required version (passed through $fieldArgs['branch'])
     *
     * @param string $fieldName
     * @param array $fieldArgs
     * @return boolean
     */
    public function resolveCanProcess($fieldResolver, string $fieldName, array $fieldArgs = []): bool;
    public function resolveSchemaValidationErrorDescription($fieldResolver, string $fieldName, array $fieldArgs = []): ?string;

    /**
     * Get the "schema" properties as for the fieldName
     *
     * @return array
     */
    public function getFieldDocumentation($fieldResolver, string $fieldName, array $fieldArgs = []): array;
    public function getFieldDocumentationType($fieldResolver, string $fieldName): ?string;
    public function getFieldDocumentationDescription($fieldResolver, string $fieldName): ?string;
    public function getFieldDocumentationArgs($fieldResolver, string $fieldName): array;
    public function enableOrderedFieldDocumentationArgs($fieldResolver, string $fieldName): bool;
    public function resolveSchemaValidationWarningDescription($fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function getFieldDocumentationDeprecationDescription($fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function resolveCanProcessResultItem($fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): bool;
    public function getValidationErrorDescription($fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string;
    public function resolveValue($fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []);
    public function resolveFieldDefaultDataloaderClass($fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
}
