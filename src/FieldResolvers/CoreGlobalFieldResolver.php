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
            'self',
            '__typename',
            'isInstanceOfType',
        ];
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $types = [
            'id' => SchemaDefinition::TYPE_ID,
            'self' => SchemaDefinition::TYPE_ID,
            '__typename' => SchemaDefinition::TYPE_STRING,
            'isInstanceOfType' => SchemaDefinition::TYPE_BOOL,
        ];
        return $types[$fieldName] ?? parent::getSchemaFieldType($typeResolver, $fieldName);
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'id' => $translationAPI->__('The object ID', 'pop-component-model'),
            'self' => $translationAPI->__('The same object', 'pop-component-model'),
            '__typename' => $translationAPI->__('The object\'s type', 'pop-component-model'),
            'isInstanceOfType' => $translationAPI->__('Indicate if the object\'s is of a given type', 'pop-component-model'),
        ];
        return $descriptions[$fieldName] ?? parent::getSchemaFieldDescription($typeResolver, $fieldName);
    }

    public function getSchemaFieldArgs(TypeResolverInterface $typeResolver, string $fieldName): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        switch ($fieldName) {
            case 'isInstanceOfType':
                return [
                    [
                        SchemaDefinition::ARGNAME_NAME => 'type',
                        SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                        SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The type name to compare against', 'component-model'),
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
            case 'self':
                return $typeResolver->getId($resultItem);
            case '__typename':
                return $typeResolver->getTypeName();
            case 'isInstanceOfType':
                $typeName = $fieldArgs['type'];
                return strtolower($typeName) == strtolower($typeResolver->getTypeName());
        }

        return parent::resolveValue($typeResolver, $resultItem, $fieldName, $fieldArgs, $variables, $expressions, $options);
    }

    public function resolveFieldTypeResolverClass(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        switch ($fieldName) {
            case 'self':
                return $typeResolver->getIdFieldTypeResolverClass();
        }
        return parent::resolveFieldTypeResolverClass($typeResolver, $fieldName, $fieldArgs);
    }
}
