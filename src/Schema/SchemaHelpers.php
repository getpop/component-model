<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\DataloadUtils;
use PoP\ComponentModel\Schema\SchemaDefinition;
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

    public static function getSchemaFieldArgNames(array $schemaFieldArgs)
    {
        return array_map(
            function($schemaFieldArg) {
                return $schemaFieldArg[SchemaDefinition::ARGNAME_NAME];
            },
            $schemaFieldArgs
        );
    }

    /**
     * Convert the field type from its internal representation (eg: "array:id") to the GraphQL standard representation (eg: "[Post]")
     *
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @param string $type
     * @return void
     */
    public static function getTypeToOutputInSchema(TypeResolverInterface $typeResolver, string $fieldName, string $type)
    {
        // Replace all instances of "array:" with wrapping the type with "[]"
        $arrayInstances = 0;
        while ($type && TypeCastingHelpers::getTypeCombinationCurrentElement($type) == SchemaDefinition::TYPE_ARRAY) {
            $arrayInstances++;
            $type = TypeCastingHelpers::getTypeCombinationNestedElements($type);
        }

        // If the type is an ID, replace it with the actual type the ID references
        if ($type == SchemaDefinition::TYPE_ID) {
            $instanceManager = InstanceManagerFacade::getInstance();
            // The type may not be implemented yet (eg: Category), then skip
            if ($fieldTypeResolverClass = DataloadUtils::getTypeResolverClassFromSubcomponentDataField($typeResolver, $fieldName)) {
                $fieldTypeResolver = $instanceManager->getInstance((string)$fieldTypeResolverClass);
                $type = $fieldTypeResolver->getTypeName();
            }
        }

        for ($i=0; $i<$arrayInstances; $i++) {
            $type = sprintf(
                '[%s]',
                $type
            );
        }
        return $type;
    }
}
