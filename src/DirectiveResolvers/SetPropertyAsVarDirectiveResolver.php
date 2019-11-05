<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Schema\TypeCastingHelpers;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

class SetPropertyAsVarDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    const DIRECTIVE_NAME = 'setPropertyAsVar';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    /**
     * This directive must go after ResolveValueAndMerge
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::BACK;
    }

    /**
     * Can set several properties
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool
    {
        return true;
    }

    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return $translationAPI->__('Extract a property from the current object, and set it as a variable, so it can be accessed by fieldValueResolvers', 'component-model');
    }

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            [
                SchemaDefinition::ARGNAME_NAME => 'properties',
                SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_STRING),
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The property in the current object from which to copy the data into the variables', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'variables',
                SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_STRING),
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Name of the variable. If not provided, the same name as the property is used', 'component-model'),
            ],
        ];
    }

    /**
     * Copy the data under the relational object into the current object
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
        // Create a custom $variables containing all the properties from $dbItems for this resultItem
        // This way, when encountering $propName in a fieldArg in a fieldValueResolver, it can resolve that value
        // Otherwise it can't, since the fieldValueResolver doesn't have access to $dbItems
        // Send a message to the resolveAndMerge directive, indicating which properties to retrieve
        $messageDirectiveName = ResolveValueAndMergeDirectiveResolver::getDirectiveName();
        $messageName = ResolveValueAndMergeDirectiveResolver::MESSAGE_RESULT_ITEM_VARIABLES;
        $properties = $this->directiveArgsForSchema['properties'];
        $variableNames = $this->directiveArgsForSchema['variables'] ?? $properties;
        foreach (array_keys($idsDataFields) as $id) {
            for ($i=0; $i<count($properties); $i++) {
                $messages[$messageDirectiveName][$messageName][$id][$variableNames[$i]] = $dbItems[(string)$id][$properties[$i]];
            }
        }
    }
}
