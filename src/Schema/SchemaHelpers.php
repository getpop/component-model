<?php
namespace PoP\ComponentModel\Schema;

use InvalidArgumentException;
use PoP\ComponentModel\DataloadUtils;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;

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

    /**
     * Convert the field type from its internal representation (eg: "array:id") to the GraphQL standard representation (eg: "[Post]")
     *
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @param string $type
     * @return void
     */
    public static function getFieldTypeToOutputInSchema(string $type, TypeResolverInterface $typeResolver, string $fieldName, ?bool $isMandatory = false): string
    {
        list (
            $arrayInstances,
            $convertedType
        ) = self::getTypeComponents($type);

        // If the type is an ID, replace it with the actual type the ID references
        if ($convertedType == SchemaDefinition::TYPE_ID) {
            $instanceManager = InstanceManagerFacade::getInstance();
            // The convertedType may not be implemented yet (eg: Category), then skip
            if ($fieldTypeResolverClass = $typeResolver->resolveFieldTypeResolverClass($fieldName)) {
                $fieldTypeResolver = $instanceManager->getInstance((string)$fieldTypeResolverClass);
                $convertedType = $fieldTypeResolver->getTypeName();
            }
        }

        return self::convertTypeToSDLSyntax($arrayInstances, $convertedType, $isMandatory);
    }
    public static function getFieldOrDirectiveArgTypeToOutputInSchema(string $type, ?bool $isMandatory = false): string
    {
        list (
            $arrayInstances,
            $convertedType
        ) = self::getTypeComponents($type);

        return self::convertTypeToSDLSyntax($arrayInstances, $convertedType, $isMandatory);
    }
    public static function convertTypeNameToGraphQLStandard(string $typeName): string
    {
        // If the type is a scalar value, we need to convert it to the official GraphQL type
        $graphQLScalarTypes = [
            SchemaDefinition::TYPE_UNRESOLVED_ID => 'ID',
            SchemaDefinition::TYPE_STRING => 'String',
            SchemaDefinition::TYPE_INT => 'Int',
            SchemaDefinition::TYPE_FLOAT => 'Float',
            SchemaDefinition::TYPE_BOOL => 'Boolean',
        ];
        $convertToTitleCaseTypes = [
            SchemaDefinition::TYPE_OBJECT,
            SchemaDefinition::TYPE_MIXED,
            SchemaDefinition::TYPE_DATE,
            SchemaDefinition::TYPE_TIME,
            SchemaDefinition::TYPE_URL,
            SchemaDefinition::TYPE_EMAIL,
            SchemaDefinition::TYPE_IP,
        ];
        if (isset($graphQLScalarTypes[$typeName])) {
            $typeName = $graphQLScalarTypes[$typeName];
        } elseif (in_array($typeName, $convertToTitleCaseTypes)) {
            // Otherwise, by convention, convert the type name to title case
            $typeName = ucfirst($typeName);
        }

        return $typeName;
    }
    protected static function getTypeComponents(string $type): array
    {
        $convertedType = $type;

        // Replace all instances of "array:" with wrapping the type with "[]"
        $arrayInstances = 0;
        while ($convertedType && TypeCastingHelpers::getTypeCombinationCurrentElement($convertedType) == SchemaDefinition::TYPE_ARRAY) {
            $arrayInstances++;
            $convertedType = TypeCastingHelpers::getTypeCombinationNestedElements($convertedType);
        }

        // If the type was actually only "array", without indicating its type, by now $type will be null
        // In that case, inform of the error (an array cannot have its inner type undefined)
        if (!$convertedType) {
            $translationAPI = TranslationAPIFacade::getInstance();
            throw new InvalidArgumentException(
                sprintf(
                    $translationAPI->__('Type \'%s\' doesn\'t declare the type of the innermost element'),
                    $type
                )
            );
        }

        // Convert the type name to standards by GraphQL
        $convertedType = self::convertTypeNameToGraphQLStandard($convertedType);

        return [
            $arrayInstances,
            $convertedType
        ];
    }
    protected static function convertTypeToSDLSyntax(int $arrayInstances, string $convertedType, ?bool $isMandatory = false): string
    {
        // Wrap the type with the array brackets
        for ($i=0; $i<$arrayInstances; $i++) {
            $convertedType = sprintf(
                '[%s]',
                $convertedType
            );
        }
        if ($isMandatory) {
            $convertedType .= '!';
        }
        return $convertedType;
    }
}
