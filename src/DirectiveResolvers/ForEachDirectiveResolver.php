<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

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

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return array_merge(
            parent::getSchemaDirectiveArgs($fieldResolver),
            [
                [
                    SchemaDefinition::ARGNAME_NAME => 'if',
                    SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_BOOL,
                    SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('If provided, iterate only those items that satisfy this condition `%s`', 'component-model'),
                ],
            ]
        );
    }

    /**
     * Iterate on all items from the array
     *
     * @param array $value
     * @return void
     */
    protected function getArrayItems(array &$array, $id, string $field, DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$previousDBItems, array &$variables, array &$messages): ?array
    {
        if ($if = $this->directiveArgsForSchema['if']) {
            // If it is a field, execute the function against all the values in the array
            // Those that satisfy the condition stay, the others are filtered out
            // We must add each item in the array as variable `value`, over which the if function can be evaluated
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            if ($fieldQueryInterpreter->isFieldArgumentValueAField($if)) {
                $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                $options = [
                    AbstractFieldResolver::OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM => true,
                ];
                $arrayItems = [];
                foreach ($array as $key => $value) {
                    $this->addVariableValueForResultItem($id, 'value', $value, $messages);
                    $resultItemVariables = $this->getVariablesForResultItem($id, $variables, $messages);
                    if ($ifValue = $fieldResolver->resolveValue($resultIDItems[(string)$id], $if, $resultItemVariables, $options)) {
                        $arrayItems[$key] = $value;
                    }
                }
                return $arrayItems;
            }
        }
        return $array;
    }
}
