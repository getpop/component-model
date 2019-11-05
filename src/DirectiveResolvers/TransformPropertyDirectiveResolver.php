<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class TransformPropertyDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    const DIRECTIVE_NAME = 'transformProperty';
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
     * Can copy several values
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
        return $translationAPI->__('Transform the value of a property in the current object, optionally storing the transformation under a different property', 'component-model');
    }

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            [
                SchemaDefinition::ARGNAME_NAME => 'property',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The property in the relational object to transform', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'function',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Transformation function', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'parameter',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The parameter under which to pass the object\'s property value to the transformation function. If not provided, the value is added as the first field argument, without a name (expecting it can be deduced from the schema)', 'component-model'),
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'target',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The property under which to store the transformation. If not provided, the \'property\' field is overriden with the new value', 'component-model'),
            ],
        ];
    }

    /**
     * Transform a property from the current object
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
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        $property = $this->directiveArgsForSchema['property'];
        $function = $this->directiveArgsForSchema['function'];
        $parameter = $this->directiveArgsForSchema['parameter'];
        $target = $this->directiveArgsForSchema['target'] ?? $property;

        // Insert the value under the property name, or in first position
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $functionName = $fieldQueryInterpreter->getFieldName($function);
        $functionArgElems = $fieldQueryInterpreter->extractFieldArguments($fieldResolver, $function);

        // Get the value from the object
        foreach (array_keys($idsDataFields) as $id) {
            $value = $dbItems[(string)$id][$property];
            $resultItemFunctionArgElems = $functionArgElems;
            if ($parameter) {
                $resultItemFunctionArgElems[$parameter] = $value;
            } else {
                array_unshift($resultItemFunctionArgElems, $value);
            }

            // Regenerate the function, execute it, and replace the value in the DB
            $resultItemFunction = $fieldQueryInterpreter->getField($functionName, $resultItemFunctionArgElems);
            $dbItems[(string)$id][$target] = $fieldResolver->resolveValue($resultIDItems[(string)$id], $resultItemFunction);
        }
    }
}
