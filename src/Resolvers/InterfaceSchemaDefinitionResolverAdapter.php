<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Resolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\FieldResolvers\FieldSchemaDefinitionResolverInterface;
use PoP\ComponentModel\FieldInterfaceResolvers\FieldInterfaceResolverInterface;

class InterfaceSchemaDefinitionResolverAdapter implements FieldSchemaDefinitionResolverInterface
{
    private $fieldInterfaceResolver;

    public function __construct(FieldInterfaceResolverInterface $fieldInterfaceResolver)
    {
        $this->fieldInterfaceResolver = $fieldInterfaceResolver;
    }

    /**
     * This function will never be called for the Adapter,
     * but must be implemented to satisfy the interface
     *
     * @return array
     */
    public static function getFieldNamesToResolve(): array
    {
        return [];
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return $this->fieldInterfaceResolver->getSchemaFieldType($typeResolver, $fieldName);
    }

    public function isSchemaFieldResponseNonNullable(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return $this->fieldInterfaceResolver->isSchemaFieldResponseNonNullable($typeResolver, $fieldName);
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return $this->fieldInterfaceResolver->getSchemaFieldDescription($typeResolver, $fieldName);
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        return $this->fieldInterfaceResolver->getSchemaFieldArgs($typeResolver, $fieldName);
    }

    public function getFilteredSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        return $this->fieldInterfaceResolver->getFilteredSchemaFieldArgs($typeResolver, $fieldName);
    }

    public function getSchemaFieldDeprecationDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return $this->fieldInterfaceResolver->getSchemaFieldDeprecationDescription($typeResolver, $fieldName, $fieldArgs);
    }

    public function addSchemaDefinitionForField(array &$schemaDefinition, TypeResolverInterface $typeResolver, string $fieldName): void
    {
        $this->fieldInterfaceResolver->addSchemaDefinitionForField($schemaDefinition, $typeResolver, $fieldName);
    }
}
