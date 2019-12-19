<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\FieldResolvers\FieldSchemaDefinitionResolverInterface;

trait SelfSchemaDefinitionResolverTrait
{
    /**
     * The object resolves its own schema definition
     *
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @param array $fieldArgs
     * @return void
     */
    public function getSchemaDefinitionResolver(TypeResolverInterface $typeResolver): ?FieldSchemaDefinitionResolverInterface
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

    public function isGlobal(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return false;
    }
}
