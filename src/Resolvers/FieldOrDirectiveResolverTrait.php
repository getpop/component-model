<?php

declare(strict_types=1);

namespace PoP\ComponentModel\Resolvers;

use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;

trait FieldOrDirectiveResolverTrait
{
    protected $enumValueArgumentValidationCache = [];

    protected function validateEnumFieldOrDirectiveArguments(array $enumArgs, string $fieldOrDirectiveName, array $fieldOrDirectiveArgs = []): array
    {
        $key = serialize($enumArgs) . '|' . $fieldOrDirectiveName . serialize($fieldOrDirectiveArgs);
        if (!isset($this->enumValueArgumentValidationCache[$key])) {
            $this->enumValueArgumentValidationCache[$key] = $this->doValidateEnumFieldOrDirectiveArguments($enumArgs, $fieldOrDirectiveName, $fieldOrDirectiveArgs);
        }
        return $this->enumValueArgumentValidationCache[$key];
    }
    protected function doValidateEnumFieldOrDirectiveArguments(array $enumArgs, string $fieldOrDirectiveName, array $fieldOrDirectiveArgs = []): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $errors = $deprecations = [];
        $fieldOrDirectiveArgumentNames = SchemaHelpers::getSchemaFieldArgNames($enumArgs);
        $schemaFieldArgumentEnumValueDefinitions = SchemaHelpers::getSchemaFieldArgEnumValueDefinitions($enumArgs);
        for ($i = 0; $i < count($fieldOrDirectiveArgumentNames); $i++) {
            $fieldOrDirectiveArgumentName = $fieldOrDirectiveArgumentNames[$i];
            $fieldOrDirectiveArgumentValue = $fieldOrDirectiveArgs[$fieldOrDirectiveArgumentName];
            if (!is_null($fieldOrDirectiveArgumentValue)) {
                // Each fieldArgumentEnumValue is an array with item "name" for sure, and maybe also "description", "deprecated" and "deprecationDescription"
                $schemaFieldOrDirectiveArgumentEnumValues = $schemaFieldArgumentEnumValueDefinitions[$fieldOrDirectiveArgumentName];
                $fieldOrDirectiveArgumentValueDefinition = $schemaFieldOrDirectiveArgumentEnumValues[$fieldOrDirectiveArgumentValue];
                if (is_null($fieldOrDirectiveArgumentValueDefinition)) {
                    // Remove deprecated ones and extract their names
                    $fieldOrDirectiveArgumentEnumValues = SchemaHelpers::removeDeprecatedEnumValuesFromSchemaDefinition($schemaFieldOrDirectiveArgumentEnumValues);
                    $fieldOrDirectiveArgumentEnumValues = array_keys($fieldOrDirectiveArgumentEnumValues);
                    $errors[] = sprintf(
                        $translationAPI->__('Value \'%s\' for argument \'%s\' in field \'%s\' is not allowed (the only allowed values are: \'%s\')', 'component-model'),
                        $fieldOrDirectiveArgumentValue,
                        $fieldOrDirectiveArgumentName,
                        $fieldOrDirectiveName,
                        implode($translationAPI->__('\', \''), $fieldOrDirectiveArgumentEnumValues)
                    );
                } elseif ($fieldOrDirectiveArgumentValueDefinition[SchemaDefinition::ARGNAME_DEPRECATED]) {
                    // Check if this enumValue is deprecated
                    $deprecations[] = sprintf(
                        $translationAPI->__('Value \'%s\' for argument \'%s\' in field \'%s\' is deprecated: \'%s\'', 'component-model'),
                        $fieldOrDirectiveArgumentValue,
                        $fieldOrDirectiveArgumentName,
                        $fieldOrDirectiveName,
                        $fieldOrDirectiveArgumentValueDefinition[SchemaDefinition::ARGNAME_DEPRECATIONDESCRIPTION]
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
