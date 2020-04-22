<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Resolvers;

use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;

trait FieldOrDirectiveResolverTrait
{
    protected $enumValueArgumentValidationCache = [];

    protected function validateEnumFieldArguments(array $enumArgs, string $fieldName, array $fieldArgs = []): array
    {
        $key = serialize($enumArgs) . '|' . $fieldName . serialize($fieldArgs);
        if (!isset($this->enumValueArgumentValidationCache[$key])) {
            $this->enumValueArgumentValidationCache[$key] = $this->doValidateEnumFieldArguments($enumArgs, $fieldName, $fieldArgs);
        }
        return $this->enumValueArgumentValidationCache[$key];
    }
    protected function doValidateEnumFieldArguments(array $enumArgs, string $fieldName, array $fieldArgs = []): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $errors = $deprecations = [];
        $fieldArgumentNames = SchemaHelpers::getSchemaFieldArgNames($enumArgs);
        $schemaFieldArgumentEnumValueDefinitions = SchemaHelpers::getSchemaFieldArgEnumValueDefinitions($enumArgs);
        for ($i = 0; $i < count($fieldArgumentNames); $i++) {
            $fieldArgumentName = $fieldArgumentNames[$i];
            $fieldArgumentValue = $fieldArgs[$fieldArgumentName];
            if (!is_null($fieldArgumentValue)) {
                // Each fieldArgumentEnumValue is an array with item "name" for sure, and maybe also "description", "deprecated" and "deprecationDescription"
                $schemaFieldArgumentEnumValues = $schemaFieldArgumentEnumValueDefinitions[$fieldArgumentName];
                $fieldArgumentValueDefinition = $schemaFieldArgumentEnumValues[$fieldArgumentValue];
                if (is_null($fieldArgumentValueDefinition)) {
                    // Remove deprecated ones and extract their names
                    $fieldArgumentEnumValues = SchemaHelpers::removeDeprecatedEnumValuesFromSchemaDefinition($schemaFieldArgumentEnumValues);
                    $fieldArgumentEnumValues = array_keys($fieldArgumentEnumValues);
                    $errors[] = sprintf(
                        $translationAPI->__('Value \'%s\' for argument \'%s\' in field \'%s\' is not allowed (the only allowed values are: \'%s\')', 'component-model'),
                        $fieldArgumentValue,
                        $fieldArgumentName,
                        $fieldName,
                        implode($translationAPI->__('\', \''), $fieldArgumentEnumValues)
                    );
                } elseif ($fieldArgumentValueDefinition[SchemaDefinition::ARGNAME_DEPRECATED]) {
                    // Check if this enumValue is deprecated
                    $deprecations[] = sprintf(
                        $translationAPI->__('Value \'%s\' for argument \'%s\' in field \'%s\' is deprecated: \'%s\'', 'component-model'),
                        $fieldArgumentValue,
                        $fieldArgumentName,
                        $fieldName,
                        $fieldArgumentValueDefinition[SchemaDefinition::ARGNAME_DEPRECATIONDESCRIPTION]
                    );
                }
            }
        }
        // if ($errors) {
        //     return implode($translationAPI->__('. '), $errors);
        // }
        // Array of 2 items: errors and deprecations
        return [
            $errors ? implode($translationAPI->__('. '), $errors) : null,
            $deprecations ? implode($translationAPI->__('. '), $deprecations) : null,
        ];
    }
}
