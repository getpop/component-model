<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

class ForEachDirectiveResolver extends AbstractApplyNestedDirectivesOnArrayItemsDirectiveResolver
{
    public const DIRECTIVE_NAME = 'forEach';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return $translationAPI->__('Iterate all affected array items and execute the nested directives on them', 'component-model');
    }

    /**
     * Iterate on all items from the array
     *
     * @param array $value
     * @return void
     */
    protected function &getArrayItems(array &$array, $id, string $field, DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$dbErrors, array &$dbWarnings): ?array
    {
        return $array;
    }
}
