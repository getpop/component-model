<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\FieldQuery\QueryHelpers;
use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class TransformPropertyDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    public const VARIABLE_VALUE = 'value';
    public const DIRECTIVE_NAME = 'transformProperty';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    /**
     * By default, this directive goes after ResolveValueAndMerge
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::BACK;
    }

    /**
     * Most likely, this directive can be executed several times
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool
    {
        return true;
    }

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            [
                SchemaDefinition::ARGNAME_NAME => 'function',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Function to execute on the affected fields', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'addParams',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_ARRAY,
                SchemaDefinition::ARGNAME_DESCRIPTION => sprintf(
                    $translationAPI->__('Parameters to inject to the function. The value of the affected field can be provided under special variable `%s`', 'component-model'),
                    QueryHelpers::getVariableQuery(self::VARIABLE_VALUE)
                ),
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'target',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('Property from the current object where to store the results of the function. If not provided, it uses the same as the affected field. If the result must not be stored, pass an empty value', 'component-model'),
            ],
        ];
    }

    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        $this->regenerateAndExecuteFunction($dataloader, $fieldResolver, $resultIDItems, $idsDataFields, $dbItems, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations, $previousDBItems, $variables, $messages);
    }

    /**
     * Execute a function on the affected field
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
    protected function regenerateAndExecuteFunction(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        $function = $this->directiveArgsForSchema['function'];
        $addParams = $this->directiveArgsForSchema['addParams'] ?? [];
        $target = $this->directiveArgsForSchema['target'];

        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        // Maybe re-generate the function: Inject the provided `$addParams` to the fieldArgs already declared in the query
        if ($addParams) {
            $functionName = $fieldQueryInterpreter->getFieldName($function);
            $functionArgElems = array_merge(
                $fieldQueryInterpreter->extractFieldArguments($fieldResolver, $function),
                $addParams
            );
            $function = $fieldQueryInterpreter->getField($functionName, $functionArgElems);
        }
        $dbKey = $dataloader->getDatabaseKey();

        // Get the value from the object
        foreach ($idsDataFields as $id => $dataFields) {
            foreach ($dataFields['direct'] as $field) {
                $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);

                // Validate that the property exists
                $isValueInDBItems = array_key_exists($fieldOutputKey, $dbItems[(string)$id] ?? []);
                if (!$isValueInDBItems && !array_key_exists($fieldOutputKey, $previousDBItems[$dbKey][(string)$id] ?? [])) {
                    if ($fieldOutputKey != $field) {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('Field \'%s\' (with output key \'%s\') hadn\'t been set for object with ID \'%s\', so it can\'t be transformed', 'component-model'),
                            $field,
                            $fieldOutputKey,
                            $id
                        );
                    } else {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('Field \'%s\' hadn\'t been set for object with ID \'%s\', so it can\'t be transformed', 'component-model'),
                            $fieldOutputKey,
                            $id
                        );
                    }
                    continue;
                }

                // Place the value (and maybe the key, if it comes from unpacking an array) into the $variables context
                $value = $isValueInDBItems ?
                    $dbItems[(string)$id][$fieldOutputKey] :
                    $previousDBItems[$dbKey][(string)$id][$fieldOutputKey];
                // $this->addVariableValueForResultItem($id, 'fieldOutputKey', $fieldOutputKey, $messages);
                if ($key = '') {
                    $this->addVariableValueForResultItem($id, 'key', $key, $messages);
                }
                $this->addVariableValueForResultItem($id, 'value', $value, $messages);

                // Finally execute the function on this field
                // $this->executeFunction($dataloader, $fieldResolver, $id, $field, $function, $resultIDItems, $dbItems, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations, $previousDBItems, $variables, $messages);
                $resultItemVariables = $this->getVariablesForResultItem($id, $variables, $messages);
                list(
                    $schemaValidField,
                    $schemaFieldName,
                    $schemaFieldArgs,
                    $schemaDBErrors,
                    $schemaDBWarnings
                ) = $fieldQueryInterpreter->extractFieldArgumentsForSchema($fieldResolver, $function, $resultItemVariables);
                // Place the errors not under schema but under DB, since they may change on a resultItem by resultItem basis
                if ($schemaDBWarnings) {
                    foreach ($schemaDBWarnings as $warningMessage) {
                        $dbWarnings[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('%s (Generated function: \'%s\')', 'component-model'),
                            $warningMessage,
                            $function
                        );
                    }
                }
                if ($schemaDBErrors) {
                    foreach ($schemaDBErrors as $errorMessage) {
                        $dbWarnings[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('%s (Generated function: \'%s\')', 'component-model'),
                            $errorMessage,
                            $function
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
                $functionValue = $fieldResolver->resolveValue($resultIDItems[(string)$id], $function, $resultItemVariables, $options);
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
                // Store the results:
                // If there is a target specified, use it
                // If the specified target is empty, then do not store the results
                // If no target was specified, use the same affected field
                $functionTarget = $target ?? $fieldOutputKey;
                if ($functionTarget) {
                    $dbItems[(string)$id][$functionTarget] = $functionValue;
                }
            }
        }
    }

    // protected function executeFunction(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, $id, string $field, string $resultItemFunction, array &$resultIDItems, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    // {
    //     $translationAPI = TranslationAPIFacade::getInstance();
    //     $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
    //     $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);

    //     $resultItemVariables = $this->getVariablesForResultItem($id, $variables, $messages);
    //     list(
    //         $schemaValidField,
    //         $schemaFieldName,
    //         $schemaFieldArgs,
    //         $schemaDBErrors,
    //         $schemaDBWarnings
    //     ) = $fieldQueryInterpreter->extractFieldArgumentsForSchema($fieldResolver, $resultItemFunction, $resultItemVariables);
    //     // Place the errors not under schema but under DB, since they may change on a resultItem by resultItem basis
    //     if ($schemaDBWarnings) {
    //         foreach ($schemaDBWarnings as $warningMessage) {
    //             $dbWarnings[(string)$id][$this->directive][] = sprintf(
    //                 $translationAPI->__('%s (Generated function: \'%s\')', 'component-model'),
    //                 $warningMessage,
    //                 $resultItemFunction
    //             );
    //         }
    //     }
    //     if ($schemaDBErrors) {
    //         foreach ($schemaDBErrors as $errorMessage) {
    //             $dbWarnings[(string)$id][$this->directive][] = sprintf(
    //                 $translationAPI->__('%s (Generated function: \'%s\')', 'component-model'),
    //                 $errorMessage,
    //                 $resultItemFunction
    //             );
    //         }
    //         $dbErrors[(string)$id][$this->directive][] = sprintf(
    //             $translationAPI->__('Transformation of fieldOutputKey \'%s\' on object with ID \'%s\' can\'t be executed due to previous errors', 'component-model'),
    //             $fieldOutputKey,
    //             $id
    //         );
    //         return;
    //     }

    //     // Execute the function, and replace the value in the DB
    //     // Because the function was dynamically created, we must indicate to validate the schema when doing ->resolveValue
    //     $options = [
    //         AbstractFieldResolver::OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM => true,
    //     ];
    //     $functionValue = $fieldResolver->resolveValue($resultIDItems[(string)$id], $resultItemFunction, $resultItemVariables, $options);
    //     // If there was an error (eg: a missing mandatory argument), then the function will be of type Error
    //     if (GeneralUtils::isError($functionValue)) {
    //         $error = $functionValue;
    //         $dbErrors[(string)$id][$this->directive][] = sprintf(
    //             $translationAPI->__('Transformation of property \'%s\' on object with ID \'%s\' failed due to error: %s', 'component-model'),
    //             $fieldOutputKey,
    //             $id,
    //             $error->getErrorMessage()
    //         );
    //         return;
    //     }
    //     $dbItems[(string)$id][$fieldOutputKey] = $functionValue;
    // }
}
