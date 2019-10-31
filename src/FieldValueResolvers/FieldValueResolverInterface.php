<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

interface FieldValueResolverInterface
{
    /**
     * Get an array with the fieldNames that this fieldValueResolver resolves
     *
     * @return array
     */
    public static function getFieldNamesToResolve(): array;

    /**
     * Indicates if the fieldValueResolver can process this combination of fieldName and fieldArgs
     * It is required to support a multiverse of fields: different fieldValueResolvers can resolve the field, based on the required version (passed through $fieldArgs['branch'])
     *
     * @param string $fieldName
     * @param array $fieldArgs
     * @return boolean
     */
    public function resolveCanProcess(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): bool;
    public function resolveSchemaValidationErrorDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function resolveValue(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []);
    public function resolveFieldDefaultDataloaderClass(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function resolveSchemaValidationWarningDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string;
    public function resolveCanProcessResultItem(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): bool;
    public function enableOrderedFieldDocumentationArgs(FieldResolverInterface $fieldResolver, string $fieldName): bool;
    public function getValidationErrorDescription(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string;
}
