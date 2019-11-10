<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class RenamePropertyDirectiveResolver extends DuplicatePropertyDirectiveResolver
{
    const DIRECTIVE_NAME = 'renameProperty';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return $translationAPI->__('Rename a property in the current object', 'component-model');
    }

    /**
     * Rename a property from the current object
     *
     * @param FieldResolverInterface $fieldResolver
     * @param array $resultIDItems
     * @param array $idsDataFields
     * @param array $dbItems
     * @param array $dbErrors
     * @param array $dbWarnings
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return void
     */
    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        // After duplicating the property, delete the original
        parent::resolveDirective($dataloader, $fieldResolver, $resultIDItems, $idsDataFields, $dbItems, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations, $previousDBItems, $variables, $messages);
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        foreach ($idsDataFields as $id => $dataFields) {
            foreach ($dataFields['direct'] as $field) {
                $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                unset($dbItems[(string)$id][$fieldOutputKey]);
            }
        }
    }
}
