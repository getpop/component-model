<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class ForEachDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    const DIRECTIVE_NAME = 'forEach';
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
     * Can execute several functions through the forEach
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
        return $translationAPI->__('Iterate through the elements of an array and execute a function on each element', 'component-model');
    }

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            [
                SchemaDefinition::ARGNAME_NAME => 'function',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Function to execute on each item of the array', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'parameter',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The parameter under which to pass the array object to the function. If not provided, the value is added as the first field argument, without a name (expecting it can be deduced from the schema)', 'component-model'),
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'addParams',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_ARRAY,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Provide extra parameters to the function', 'component-model'),
            ],
        ];
    }

    /**
     * Iterate through all the elements of an array, and execute a function on each of them
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

        $function = $this->directiveArgsForSchema['function'];
        $parameter = $this->directiveArgsForSchema['parameter'];
        $addParams = $this->directiveArgsForSchema['addParams'] ?? [];

        // Insert the value under the property name, or in first position
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $functionName = $fieldQueryInterpreter->getFieldName($function);
        $functionArgElems = $fieldQueryInterpreter->extractFieldArguments($fieldResolver, $function);

        $functionArgElems = array_merge(
            $functionArgElems,
            $addParams
        );

        // Get the value from the object
        foreach ($idsDataFields as $id => $dataFields) {
            foreach ($dataFields['direct'] as $field) {
                $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                $array = $dbItems[(string)$id][$fieldOutputKey];
                $resultArray = [];
                foreach ($array as $key => $value) {
                    $resultItemFunctionArgElems = $functionArgElems;
                    if ($parameter) {
                        $resultItemFunctionArgElems[$parameter] = $value;
                    } else {
                        array_unshift($resultItemFunctionArgElems, $value);
                    }

                    // Regenerate the function
                    $resultItemFunction = $fieldQueryInterpreter->getField($functionName, $resultItemFunctionArgElems);

                    // Validate the new fieldArgs once again, to make sure the addition of the new parameter is right (eg: maybe the param name where to pass the function is wrong)
                    // Add the special variables `$key` and `$value` from the iteration
                    $this->addVariableValueForResultItem($id, 'key', $key, $messages);
                    $this->addVariableValueForResultItem($id, 'value', $value, $messages);
                    $resultItemVariables = $this->getVariablesForResultItem($id, $variables, $messages);
                    list(
                        $schemaValidField,
                        $schemaFieldName,
                        $schemaFieldArgs,
                        $schemaDBErrors,
                        $schemaDBWarnings
                    ) = $fieldQueryInterpreter->extractFieldArgumentsForSchema($fieldResolver, $resultItemFunction, $resultItemVariables);
                    // Place the errors not under schema but under DB, since they may change on a resultItem by resultItem basis
                    if ($schemaDBWarnings) {
                        foreach ($schemaDBWarnings as $warningMessage) {
                            $dbWarnings[(string)$id][$this->directive][] = sprintf(
                                $translationAPI->__('%s (Generated function: \'%s\')', 'component-model'),
                                $warningMessage,
                                $resultItemFunction
                            );
                        }
                    }
                    if ($schemaDBErrors) {
                        foreach ($schemaDBErrors as $errorMessage) {
                            $dbWarnings[(string)$id][$this->directive][] = sprintf(
                                $translationAPI->__('%s (Generated function: \'%s\')', 'component-model'),
                                $errorMessage,
                                $resultItemFunction
                            );
                        }
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('Transformation of fieldOutputKey \'%s\' on object with ID \'%s\' can\'t be executed due to previous errors', 'component-model'),
                            $fieldOutputKey,
                            $id
                        );
                        continue;
                    }

                    // Execute the function, and replace the value in the DB
                    // Because the function was dynamically created, we must indicate to validate the schema when doing ->resolveValue
                    $options = [
                        AbstractFieldResolver::OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM => true,
                    ];
                    $functionValue = $fieldResolver->resolveValue($resultIDItems[(string)$id], $resultItemFunction, $resultItemVariables, $options);
                    // If there was an error (eg: a missing mandatory argument), then the function will be of type Error
                    if (GeneralUtils::isError($functionValue)) {
                        $error = $functionValue;
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('Transformation of property \'%s\' on object with ID \'%s\' failed due to error: %s', 'component-model'),
                            $fieldOutputKey,
                            $id,
                            $error->getErrorMessage()
                        );
                        continue;
                    }
                    $resultArray[$key] = $functionValue;
                }
                $dbItems[(string)$id][$fieldOutputKey] = $resultArray;
            }
        }
    }
}
