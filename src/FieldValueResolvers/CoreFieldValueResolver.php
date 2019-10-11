<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use \PoP\ComponentModel\FieldResolverBase;

class CoreFieldValueResolver extends AbstractDBDataFieldValueResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
            FieldResolverBase::class,
        ];
    }

    public function getFieldNamesToResolve(): array
    {
        return [
            'id',
        ];
    }

    public function getFieldDocumentationType(string $fieldName): ?string
    {
        $types = [
            'id' => SchemaDefinition::TYPE_ID,
        ];
        return $types[$fieldName];
    }

    public function getFieldDocumentationDescription(string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'id' => $translationAPI->__('The DB Object ID', 'pop-component-model'),
        ];
        return $descriptions[$fieldName];
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
