<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\FieldResolvers\FieldSchemaDefinitionResolverInterface;
use PoP\ComponentModel\Schema\WithVersionConstraintFieldOrDirectiveResolverTrait;

trait SelfSchemaDefinitionResolverTrait
{
    use WithVersionConstraintFieldOrDirectiveResolverTrait;

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
        // By default, it can be of any type. Return this instead of null since the type is mandatory for GraphQL, so we avoid its non-implementation by the developer to throw errors
        return SchemaDefinition::TYPE_MIXED;
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return null;
    }

    public function getSchemaFieldVersion(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return null;
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        return [];
    }

    public function getFilteredSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        $schemaFieldArgs = $this->getSchemaFieldArgs($typeResolver, $fieldName);
        /**
         * Add the "versionConstraint" param. Add it at the end, so it doesn't affect the order of params for "orderedSchemaFieldArgs"
         */
        $this->maybeAddVersionConstraintSchemaFieldOrDirectiveArg($schemaFieldArgs);
        return $schemaFieldArgs;
    }

    public function getSchemaFieldDeprecationDescription(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        return null;
    }

    public function addSchemaDefinitionForField(array &$schemaDefinition, TypeResolverInterface $typeResolver, string $fieldName): void
    {
    }
}
