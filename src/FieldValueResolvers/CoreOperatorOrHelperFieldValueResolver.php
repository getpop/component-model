<?php
namespace PoP\ComponentModel\FieldValueResolvers;

use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\setSelfAsVarDirectiveResolver;

class CoreOperatorOrHelperFieldValueResolver extends AbstractOperatorOrHelperFieldValueResolver
{
    public static function getFieldNamesToResolve(): array
    {
        return [
            'getPropertyFromSelf',
        ];
    }

    public function getSchemaFieldType(FieldResolverInterface $fieldResolver, string $fieldName): ?string
    {
        $types = [
            'getPropertyFromSelf' => SchemaDefinition::TYPE_MIXED,
        ];
        return $types[$fieldName] ?? parent::getSchemaFieldType($fieldResolver, $fieldName);
    }

    public function getSchemaFieldDescription(FieldResolverInterface $fieldResolver, string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'getPropertyFromSelf' => sprintf(
                $translationAPI->__('Get a property from the current object, as stored under variable `$%s`', 'pop-component-model'),
                setSelfAsVarDirectiveResolver::VARIABLE_SELF
            ),
        ];
        return $descriptions[$fieldName] ?? parent::getSchemaFieldDescription($fieldResolver, $fieldName);
    }

    public function getSchemaFieldArgs(FieldResolverInterface $fieldResolver, string $fieldName): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        switch ($fieldName) {
            case 'getPropertyFromSelf':
                return [
                    [
                        SchemaDefinition::ARGNAME_NAME => 'self',
                        SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                        SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The `$self` object containing all data for the current object', 'component-model'),
                        SchemaDefinition::ARGNAME_MANDATORY => true,
                    ],
                    [
                        SchemaDefinition::ARGNAME_NAME => 'property',
                        SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                        SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The property to access from the current object', 'component-model'),
                        SchemaDefinition::ARGNAME_MANDATORY => true,
                    ],
                ];
        }

        return parent::getSchemaFieldArgs($fieldResolver, $fieldName);
    }

    public function resolveValue(FieldResolverInterface $fieldResolver, $resultItem, string $fieldName, array $fieldArgs = [])
    {
        switch ($fieldName) {
            case 'getPropertyFromSelf':
                // Retrieve the property from either 'dbItems' (i.e. it was loaded during the current iteration) or 'previousDBItems' (loaded during a previous iteration)
                $self = $fieldArgs['self'];
                $property = $fieldArgs['property'];
                return array_key_exists($property, $self['dbItems']) ? $self['dbItems'][$property] : $self['previousDBItems'][$property];
        }
        return parent::resolveValue($fieldResolver, $resultItem, $fieldName, $fieldArgs);
    }
}
