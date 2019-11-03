<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

abstract class AbstractSchemaFieldValueResolver extends AbstractFieldValueResolver implements FieldValueResolverSchemaInterface
{
    /**
     * The object resolves its own schema definition
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $fieldName
     * @param array $fieldArgs
     * @return void
     */
    public function getSchemaDefinitionResolver(FieldResolverInterface $fieldResolver)
    {
        return $this;
    }

    public function getSchemaFieldType(FieldResolverInterface $fieldResolver, string $fieldName): ?string
    {
        return null;
    }

    public function getSchemaFieldDescription(FieldResolverInterface $fieldResolver, string $fieldName): ?string
    {
        return null;
    }

    public function getSchemaFieldArgs(FieldResolverInterface $fieldResolver, string $fieldName): array
    {
        return [];
    }

    public function getSchemaFieldDeprecationDescription(FieldResolverInterface $fieldResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }
}
