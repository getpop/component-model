<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

trait EnumTypeSchemaDefinitionResolverTrait
{
    protected function getSchemaDefinitionEnumValues(TypeResolverInterface $typeResolver, string $fieldName): ?array
    {
        return null;
    }

    public function addSchemaDefinitionForField(array &$schemaDefinition, TypeResolverInterface $typeResolver, string $fieldName): void
    {
        parent::addSchemaDefinitionForField($schemaDefinition, $typeResolver, $fieldName);

        $enumValues = $this->getSchemaDefinitionEnumValues($typeResolver, $fieldName);
        if (!is_null($enumValues)) {
            $schemaDefinition[SchemaDefinition::ARGNAME_ENUMVALUES] = $enumValues;
        }
    }
}
