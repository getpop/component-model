<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;

class CoreFieldValueResolver extends AbstractDBDataFieldValueResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
            AbstractFieldResolver::class,
        ];
    }

    public function getFieldNamesToResolve(): array
    {
        return [
            'id',
        ];
    }

    public function getFieldDocumentationType($fieldResolver, string $fieldName): ?string
    {
        $types = [
            'id' => SchemaDefinition::TYPE_ID,
        ];
        return $types[$fieldName] ?? parent::getFieldDocumentationType($fieldResolver, $fieldName);
    }

    public function getFieldDocumentationDescription($fieldResolver, string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'id' => $translationAPI->__('The DB Object ID', 'pop-component-model'),
        ];
        return $descriptions[$fieldName] ?? parent::getFieldDocumentationDescription($fieldResolver, $fieldName);
    }

    public function resolveValue($fieldResolver, $resultItem, string $fieldName, array $fieldArgs = [])
    {
        switch ($fieldName) {
            case 'id':
                return $fieldResolver->getId($resultItem);
        }

        return parent::resolveValue($fieldResolver, $resultItem, $fieldName, $fieldArgs);
    }

    public function resolveFieldDefaultDataloaderClass($fieldResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        switch ($fieldName) {
            case 'id':
                return $fieldResolver->getIdFieldDataloaderClass();
        }
        return parent::resolveFieldDefaultDataloaderClass($fieldResolver, $fieldName, $fieldArgs);
    }
}
