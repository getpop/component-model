<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Schema\SchemaDefinition;

class SchemaHelpers
{
    public static function getMissingFieldArgs(array $fieldArgProps, array $fieldArgs): array {
        return array_values(array_filter(
            $fieldArgProps,
            function($fieldArgProp) use($fieldArgs) {
                return !array_key_exists($fieldArgProp, $fieldArgs);
            }
        ));
    }

    public static function getSchemaMandatoryFieldArgs(array $schemaFieldArgs)
    {
        return array_filter(
            $schemaFieldArgs,
            function($schemaFieldArg) {
                return isset($schemaFieldArg[SchemaDefinition::ARGNAME_MANDATORY]) && $schemaFieldArg[SchemaDefinition::ARGNAME_MANDATORY];
            }
        );
    }

    public static function getSchemaEnumTypeFieldArgs(array $schemaFieldArgs)
    {
        return array_filter(
            $schemaFieldArgs,
            function($schemaFieldArg) {
                return isset($schemaFieldArg[SchemaDefinition::ARGNAME_TYPE]) && $schemaFieldArg[SchemaDefinition::ARGNAME_TYPE] == SchemaDefinition::TYPE_ENUM;
            }
        );
    }

    public static function getSchemaFieldArgNames(array $schemaFieldArgs)
    {
        // $schemaFieldArgs contains the name also as the key, keep only the values
        return array_values(array_map(
            function($schemaFieldArg) {
                return $schemaFieldArg[SchemaDefinition::ARGNAME_NAME];
            },
            $schemaFieldArgs
        ));
    }

    public static function convertToSchemaFieldArgEnumValueDefinitions(array $enumValues)
    {
        $enumValueDefinitions = [];
        // Create an array representing the enumValue definition
        // Since only the enumValues were defined, these have no description/deprecated data, so no need to add these either
        foreach ($enumValues as $enumValue) {
            $enumValueDefinitions[$enumValue] = [
                SchemaDefinition::ARGNAME_NAME => $enumValue,
            ];
        }
        return $enumValueDefinitions;
    }

    /**
     * Remove the deprecated enumValues from the schema definition
     *
     * @param array $enumValueDefinitions
     * @return void
     */
    public static function removeDeprecatedEnumValuesFromSchemaDefinition(array $enumValueDefinitions): array
    {
        // Remove deprecated ones
        return array_filter(
            $enumValueDefinitions,
            function($enumValueDefinition) {
                if ($enumValueDefinition[SchemaDefinition::ARGNAME_DEPRECATED]) {
                    return false;
                }
                return true;
            }
        );
    }

    public static function getSchemaFieldArgEnumValueDefinitions(array $schemaFieldArgs)
    {
        $enumValuesOrDefinitions = array_map(
            function($schemaFieldArg) {
                return $schemaFieldArg[SchemaDefinition::ARGNAME_ENUMVALUES];
            },
            $schemaFieldArgs
        );
        if (!$enumValuesOrDefinitions) {
            return [];
        }
        $enumValueDefinitions = [];
        foreach ($enumValuesOrDefinitions as $fieldArgName => $fieldArgEnumValuesOrDefinitions) {
            // The array is either an array of elemValues (eg: ["first", "second"]) or an array of elemValueDefinitions (eg: ["first" => ["name" => "first"], "second" => ["name" => "second"]])
            // To tell one from the other, check if the first element from the array is itself an array. In that case, it's a definition. Otherwise, it's already the value.
            $firstElemKey = key($fieldArgEnumValuesOrDefinitions);
            if (is_array($fieldArgEnumValuesOrDefinitions[$firstElemKey])) {
                $enumValueDefinitions[$fieldArgName] = $fieldArgEnumValuesOrDefinitions;
            } else {
                // Create an array representing the enumValue definition
                // Since only the enumValues were defined, these have no description/deprecated data, so no need to add these either
                foreach ($fieldArgEnumValuesOrDefinitions as $enumValue) {
                    $enumValueDefinitions[$fieldArgName][$enumValue] = [
                        SchemaDefinition::ARGNAME_NAME => $enumValue,
                    ];
                }
            }
        }
        return $enumValueDefinitions;
    }
}
