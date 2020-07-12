<?php

declare(strict_types=1);

namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

trait EnumTypeSchemaDefinitionResolverTrait
{
    protected function getSchemaDefinitionEnumName(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        return null;
    }

    protected function getSchemaDefinitionEnumValues(TypeResolverInterface $typeResolver, string $fieldName): ?array
    {
        return null;
    }

    protected function getSchemaDefinitionEnumValueDeprecationDescriptions(TypeResolverInterface $typeResolver, string $fieldName): ?array
    {
        return null;
    }

    protected function getSchemaDefinitionEnumValueDescriptions(TypeResolverInterface $typeResolver, string $fieldName): ?array
    {
        return null;
    }

    /**
     * Add the enum values in the schema: arrays of enum name, description, deprecated and deprecation description
     *
     * @param array $schemaDefinition
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @return void
     */
    protected function addSchemaDefinitionEnumValuesForField(array &$schemaDefinition, TypeResolverInterface $typeResolver, string $fieldName): void
    {
        $enumValues = $this->getSchemaDefinitionEnumValues($typeResolver, $fieldName);
        if (!is_null($enumValues)) {
            $enumValueDeprecationDescriptions = $this->getSchemaDefinitionEnumValueDeprecationDescriptions($typeResolver, $fieldName) ?? [];
            $enumValueDescriptions = $this->getSchemaDefinitionEnumValueDescriptions($typeResolver, $fieldName) ?? [];
            $enums = [];
            foreach ($enumValues as $enumValue) {
                $enum = [
                    SchemaDefinition::ARGNAME_NAME => $enumValue,
                ];
                if ($description = $enumValueDescriptions[$enumValue]) {
                    $enum[SchemaDefinition::ARGNAME_DESCRIPTION] = $description;
                }
                if ($deprecationDescription = $enumValueDeprecationDescriptions[$enumValue]) {
                    $enum[SchemaDefinition::ARGNAME_DEPRECATED] = true;
                    $enum[SchemaDefinition::ARGNAME_DEPRECATIONDESCRIPTION] = $deprecationDescription;
                }
                $enums[$enumValue] = $enum;
            }
            $schemaDefinition[SchemaDefinition::ARGNAME_ENUMVALUES] = $enums;
            // Indicate the unique name, to unify all types to the same Enum
            if ($enumName = $this->getSchemaDefinitionEnumName($typeResolver, $fieldName)) {
                $schemaDefinition[SchemaDefinition::ARGNAME_ENUMNAME] = $enumName;
            }
        }
    }

    public function addSchemaDefinitionForField(array &$schemaDefinition, TypeResolverInterface $typeResolver, string $fieldName): void
    {
        parent::addSchemaDefinitionForField($schemaDefinition, $typeResolver, $fieldName);

        $this->addSchemaDefinitionEnumValuesForField($schemaDefinition, $typeResolver, $fieldName);
    }
}
