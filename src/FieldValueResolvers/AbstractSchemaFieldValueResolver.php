<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

abstract class AbstractSchemaFieldValueResolver extends AbstractFieldValueResolver implements FieldValueResolverSchemaInterface
{
    /**
     * The object resolves its own schema definition
     *
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @param array $fieldArgs
     * @return void
     */
    public function getSchemaDefinitionResolver(TypeResolverInterface $typeResolver)
    {
        return $this;
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return null;
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return null;
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        return [];
    }

    public function getSchemaFieldDeprecationDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    public function isOperatorOrHelper(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return false;
    }
}
