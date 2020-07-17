<?php

declare(strict_types=1);

namespace PoP\ComponentModel\FieldInterfaceResolvers;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

trait EnumTypeFieldInterfaceSchemaDefinitionResolverTrait
{
    protected function getSchemaDefinitionEnumName(string $fieldName): ?string
    {
        return null;
    }

    protected function getSchemaDefinitionEnumValues(string $fieldName): ?array
    {
        return null;
    }

    protected function getSchemaDefinitionEnumValueDeprecationDescriptions(string $fieldName): ?array
    {
        return null;
    }

    protected function getSchemaDefinitionEnumValueDescriptions(string $fieldName): ?array
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
    protected function addSchemaDefinitionEnumValuesForField(array &$schemaDefinition, string $fieldName): void
    {
        $enumValues = $this->getSchemaDefinitionEnumValues($fieldName);
        if (!is_null($enumValues)) {
            $enumValueDeprecationDescriptions = $this->getSchemaDefinitionEnumValueDeprecationDescriptions($fieldName) ?? [];
            $enumValueDescriptions = $this->getSchemaDefinitionEnumValueDescriptions($fieldName) ?? [];
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
            $schemaDefinition[SchemaDefinition::ARGNAME_ENUM_VALUES] = $enums;
            // Indicate the unique name, to unify all types to the same Enum
            if ($enumName = $this->getSchemaDefinitionEnumName($fieldName)) {
                $schemaDefinition[SchemaDefinition::ARGNAME_ENUM_NAME] = $enumName;
            }
        }
    }

    public function addSchemaDefinitionForField(array &$schemaDefinition, string $fieldName): void
    {
        parent::addSchemaDefinitionForField($schemaDefinition, $fieldName);

        $this->addSchemaDefinitionEnumValuesForField($schemaDefinition, $fieldName);
    }
}
