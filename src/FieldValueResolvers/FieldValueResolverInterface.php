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
     * Get an instance of the object defining the schema for this fieldValueResolver
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $fieldName
     * @param array $fieldArgs
     * @return void
     */
    public function getSchemaDefinitionResolver(FieldResolverInterface $fieldResolver);
    public function getSchemaDefinitionForField(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): array;

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
    public function enableOrderedSchemaFieldArgs(FieldResolverInterface $fieldResolver, string $fieldName): bool;
    public function getValidationErrorDescription(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = []): ?string;
}
