<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\TypeResolvers\AbstractTypeResolver;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

class CoreFieldValueResolver extends AbstractDBDataFieldValueResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
            AbstractTypeResolver::class,
        ];
    }

    public static function getFieldNamesToResolve(): array
    {
        return [
            'id',
        ];
    }

    public function getSchemaFieldType(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $types = [
            'id' => SchemaDefinition::TYPE_ID,
        ];
        return $types[$fieldName] ?? parent::getSchemaFieldType($typeResolver, $fieldName);
    }

    public function getSchemaFieldDescription(TypeResolverInterface $typeResolver, string $fieldName): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $descriptions = [
            'id' => $translationAPI->__('The DB Object ID', 'pop-component-model'),
        ];
        return $descriptions[$fieldName] ?? parent::getSchemaFieldDescription($typeResolver, $fieldName);
    }

    public function resolveValue(TypeResolverInterface $typeResolver, $resultItem, string $fieldName, array $fieldArgs = [], ?array $variables = null, ?array $expressions = null, array $options = [])
    {
        switch ($fieldName) {
            case 'id':
                return $typeResolver->getId($resultItem);
        }

        return parent::resolveValue($typeResolver, $resultItem, $fieldName, $fieldArgs, $variables, $expressions, $options);
    }

    public function resolveFieldDefaultDataloaderClass(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?string
    {
        switch ($fieldName) {
            case 'id':
                return $typeResolver->getIdFieldDataloaderClass();
        }
        return parent::resolveFieldDefaultDataloaderClass($typeResolver, $fieldName, $fieldArgs);
    }
}
