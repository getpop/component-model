<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\FieldResolvers\AbstractGlobalFieldResolver;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

class CoreGlobalFieldResolver extends AbstractGlobalFieldResolver
{
    public static function getFieldNamesToResolve(): array
    {
        return [
            'id',
            '__typename',
            'isType',
            'implements',
        ];
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $types = [
            'id' => SchemaDefinition::TYPE_MIXED,
            '__typename' => SchemaDefinition::TYPE_STRING,
            'isType' => SchemaDefinition::TYPE_BOOL,
            'implements' => SchemaDefinition::TYPE_BOOL,
        ];
        return $types[$fieldName] ?? parent::getSchemaFieldType($typeResolver, $fieldName);
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'id' => $translationAPI->__('The object ID', 'pop-component-model'),
            '__typename' => $translationAPI->__('The object\'s type', 'pop-component-model'),
            'isType' => $translationAPI->__('Indicate if the object is of a given type', 'pop-component-model'),
            'implements' => $translationAPI->__('Indicate if the object implements a given interface', 'pop-component-model'),
        ];
        return $descriptions[$fieldName] ?? parent::getSchemaFieldDescription($typeResolver, $fieldName);
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        switch ($fieldName) {
            case 'isType':
                return [
                    [
                        SchemaDefinition::ARGNAME_NAME => 'type',
                        SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                        SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The type name to compare against', 'component-model'),
                        SchemaDefinition::ARGNAME_MANDATORY => true,
                    ],
                ];
            case 'implements':
                return [
                    [
                        SchemaDefinition::ARGNAME_NAME => 'interface',
                        SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                        SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The interface name to compare against', 'component-model'),
                        SchemaDefinition::ARGNAME_MANDATORY => true,
                    ],
                ];
        }

        return parent::getSchemaFieldArgs($typeResolver, $fieldName);
    }

    public function resolveValue(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = [], ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        switch ($fieldName) {
            case 'id':
                return $typeResolver->getID($resultItem);
            case '__typename':
                return $typeResolver->getTypeName();
            case 'isType':
                $typeName = $fieldArgs['type'];
                return $typeName == $typeResolver->getTypeName();
            case 'implements':
                $interface = $fieldArgs['interface'];
                $implementedInterfaceNames = array_map(
                    function($interfaceClass) {
                        return $interfaceClass::getInterfaceName();
                    },
                    $typeResolver->getAllImplementedInterfaceClasses()
                );
                return in_array($interface, $implementedInterfaceNames);
        }

        return parent::resolveValue($typeResolver, $resultItem, $fieldName, $fieldArgs, $variables, $expressions, $options);
    }
}
