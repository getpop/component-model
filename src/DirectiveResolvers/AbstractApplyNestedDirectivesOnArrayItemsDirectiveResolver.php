<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\FieldQuery\QuerySyntax;
use PoP\FieldQuery\QueryHelpers;
use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Schema\TypeCastingHelpers;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

abstract class AbstractApplyNestedDirectivesOnArrayItemsDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    public const VARIABLE_VALUE = 'value';

    /**
     * Use a value that can't be part of a fieldName, that's legible, and that conveys the meaning of sublevel. The value "." is adequate
     */
    public const PROPERTY_SEPARATOR = '.';

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
                SchemaDefinition::ARGNAME_NAME => 'addVariables',
                SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_MIXED),
                SchemaDefinition::ARGNAME_DESCRIPTION => sprintf(
                    $translationAPI->__('Variables to inject to the nested directive. The value of the affected field can be provided under special variable `%s`', 'component-model'),
                    QueryHelpers::getVariableQuery(self::VARIABLE_VALUE)
                ),
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'appendVariables',
                SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_MIXED),
                SchemaDefinition::ARGNAME_DESCRIPTION => sprintf(
                    $translationAPI->__('Append a value to an array variable, to inject to the nested directive. The value of the affected field can be provided under special variable `%s`', 'component-model'),
                    QueryHelpers::getVariableQuery(self::VARIABLE_VALUE)
                ),
            ],
        ];
    }

    /**
     * Execute directive <transformProperty> to each of the elements on the affected field, which must be an array
     * This is achieved by executing the following logic:
     * 1. Unpack the elements of the array into a temporary property for each, in the current object
     * 2. Execute <transformProperty> on each property
     * 3. Pack into the array, once again, and remove all temporary properties
     *
     * @param DataloaderInterface $dataloader
     * @param FieldResolverInterface $fieldResolver
     * @param array $resultIDItems
     * @param array $idsDataFields
     * @param array $dbItems
     * @param array $dbErrors
     * @param array $dbWarnings
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @param array $previousDBItems
     * @param array $variables
     * @param array $messages
     * @return void
     */
    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        $translationAPI = TranslationAPIFacade::getInstance();

        // If there is no nested directive pipeline, then nothing to do
        if (!$this->nestedDirectivePipeline) {
            $schemaWarnings[$this->directive][] = $translationAPI->__('No nested directives were provided, so nothing to do for this directive', 'component-model');
            return;
        }

        /**
         * Collect all ID => dataFields for the arrayItems
         */
        $arrayItemIdsProperties = [];
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $dbKey = $dataloader->getDatabaseKey();
        /**
         * Execute nested directive only if the validations do not fail
         */
        $execute = false;

        // 1. Unpack all elements of the array into a property for each
        // By making the property "propertyName:key", the "key" can be extracted and passed under variable `$key` to the function
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

                $value = $isValueInDBItems ?
                    $dbItems[(string)$id][$fieldOutputKey] :
                    $previousDBItems[$dbKey][(string)$id][$fieldOutputKey];

                // If the array is null or empty, nothing to do
                if (!$value) {
                    continue;
                }

                // Validate that the value is an array
                if (!is_array($value)) {
                    if ($fieldOutputKey != $field) {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('The value for field \'%s\' (with output key \'%s\') is not an array, so execution of this directive can\'t continue', 'component-model'),
                            $field,
                            $fieldOutputKey,
                            $id
                        );
                    } else {
                        $dbErrors[(string)$id][$this->directive][] = sprintf(
                            $translationAPI->__('The value for field \'%s\' is not an array, so execution of this directive can\'t continue', 'component-model'),
                            $fieldOutputKey,
                            $id
                        );
                    }
                    continue;
                }

                // Obtain the elements composing the field, to re-create a new field for each arrayItem
                $fieldParts = $fieldQueryInterpreter->listField($field);
                $fieldName = $fieldParts[0];
                $fieldArgs = $fieldParts[1];
                $fieldAlias = $fieldParts[2];
                $fieldSkipOutputIfNull = $fieldParts[3];
                $fieldDirectives = $fieldParts[4];

                // The value is an array. Unpack all the elements into their own property
                $array = $value;
                if ($arrayItems = $this->getArrayItems($array, $id, $field, $dataloader, $fieldResolver, $resultIDItems, $dbErrors, $dbWarnings)) {
                    $execute = true;
                    foreach ($arrayItems as $key => &$value) {
                        // Add into the $idsDataFields object for the array items
                        // Watch out: function `regenerateAndExecuteFunction` receives `$idsDataFields` and not `$idsDataFieldOutputKeys`, so then re-create the "field" assigning a new alias
                        // If it has an alias, use it. If not, use the fieldName
                        $arrayItemAlias = $this->createPropertyForArrayItem($fieldAlias ? $fieldAlias : QuerySyntax::SYMBOL_FIELDALIAS_PREFIX.$fieldName, $key);
                        $arrayItemProperty = $fieldQueryInterpreter->composeField(
                            $fieldName,
                            $fieldArgs,
                            $arrayItemAlias,
                            $fieldSkipOutputIfNull,
                            $fieldDirectives
                        );
                        $arrayItemPropertyOutputKey = $fieldQueryInterpreter->getFieldOutputKey($arrayItemProperty);
                        // Place into the current object
                        $dbItems[(string)$id][$arrayItemPropertyOutputKey] = $value;
                        // Place it into list of fields to process
                        $arrayItemIdsProperties[(string)$id]['direct'][] = $arrayItemProperty;
                    }
                    $arrayItemIdsProperties[(string)$id]['conditional'] = [];

                    // Place the reserved variables, such as `$value`, into the `$variables` context
                    $this->addVariableValuesForResultItemInContext($dataloader, $fieldResolver, $id, $field, $resultIDItems, $dbItems, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations, $previousDBItems, $variables, $messages);
                }
            }
        }

        if ($execute) {
            // 2. Execute the nested directive pipeline on all arrayItems
            $this->nestedDirectivePipeline->resolveDirectivePipeline(
                $dataloader,
                $fieldResolver,
                $resultIDItems,
                $arrayItemIdsProperties, // Here we pass the properties to the array elements!
                $dbItems,
                $dbErrors,
                $dbWarnings,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations,
                $previousDBItems,
                $variables,
                $messages
            );

            // 3. Compose the array from the results for each array item
            foreach ($idsDataFields as $id => $dataFields) {
                foreach ($dataFields['direct'] as $field) {
                    $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
                    $isValueInDBItems = array_key_exists($fieldOutputKey, $dbItems[(string)$id] ?? []);
                    $value = $isValueInDBItems ?
                        $dbItems[(string)$id][$fieldOutputKey] :
                        $previousDBItems[$dbKey][(string)$id][$fieldOutputKey];

                    // If the array is null or empty, nothing to do
                    if (!$value) {
                        continue;
                    }
                    if (!is_array($value)) {
                        continue;
                    }

                    // Obtain the elements composing the field, to re-create a new field for each arrayItem
                    $fieldParts = $fieldQueryInterpreter->listField($field);
                    $fieldName = $fieldParts[0];
                    $fieldArgs = $fieldParts[1];
                    $fieldAlias = $fieldParts[2];
                    $fieldSkipOutputIfNull = $fieldParts[3];
                    $fieldDirectives = $fieldParts[4];

                    // If there are errors, it will return null. Don't add the errors again
                    $arrayItemDBErrors = $arrayItemDBWarnings = [];
                    $array = $value;
                    $arrayItems = $this->getArrayItems($array, $id, $field, $dataloader, $fieldResolver, $resultIDItems, $arrayItemDBErrors, $arrayItemDBWarnings);
                    // The value is an array. Unpack all the elements into their own property
                    foreach ($arrayItems as $key => &$value) {
                        $arrayItemAlias = $this->createPropertyForArrayItem($fieldAlias ? $fieldAlias : QuerySyntax::SYMBOL_FIELDALIAS_PREFIX.$fieldName, $key);
                        $arrayItemProperty = $fieldQueryInterpreter->composeField(
                            $fieldName,
                            $fieldArgs,
                            $arrayItemAlias,
                            $fieldSkipOutputIfNull,
                            $fieldDirectives
                        );
                        // Place the result of executing the function on the array item
                        $arrayItemPropertyOutputKey = $fieldQueryInterpreter->getFieldOutputKey($arrayItemProperty);
                        $arrayItemValue = $dbItems[(string)$id][$arrayItemPropertyOutputKey];
                        // Remove this temporary property from $dbItems
                        unset($dbItems[(string)$id][$arrayItemPropertyOutputKey]);
                        // Validate it's not an error
                        if (GeneralUtils::isError($arrayItemValue)) {
                            $error = $arrayItemValue;
                            $dbErrors[(string)$id][$this->directive][] = sprintf(
                                $translationAPI->__('Transformation of element with key \'%s\' on array from property \'%s\' on object with ID \'%s\' failed due to error: %s', 'component-model'),
                                $key,
                                $fieldOutputKey,
                                $id,
                                $error->getErrorMessage()
                            );
                            continue;
                        }
                        // Place the result for the array in the original property
                        $dbItems[(string)$id][$fieldOutputKey][$key] = $arrayItemValue;
                    }
                }
            }
        }
    }

    /**
     * Return the items to iterate on
     *
     * @param array $value
     * @return void
     */
    abstract protected function &getArrayItems(array &$array, $id, string $field, DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$dbErrors, array &$dbWarnings): ?array;

    /**
     * Create a property for storing the array item in the current object
     *
     * @param string $fieldOutputKey
     * @param [type] $key
     * @return void
     */
    protected function createPropertyForArrayItem(string $fieldAliasOrName, $key): string
    {
        return implode(self::PROPERTY_SEPARATOR, [$fieldAliasOrName, $key]);
    }

    // protected function extractElementsFromArrayItemProperty(string $arrayItemProperty): array
    // {
    //     // Notice that we may be nesting several directives, such as <forEach<forEach<transform>>
    //     // Then, the property will contain several instances of unpacking arrayItems
    //     // For this reason, when extracting the property, obtain the right-side value from the last instance of the separator
    //     // $pos = QueryUtils::findLastSymbolPosition($arrayItemProperty, self::PROPERTY_SEPARATOR);
    //     return explode(self::PROPERTY_SEPARATOR, $arrayItemProperty);
    // }

    /**
     * Add the $key in addition to the $value
     *
     * @param DataloaderInterface $dataloader
     * @param FieldResolverInterface $fieldResolver
     * @param [type] $id
     * @param string $field
     * @param array $resultIDItems
     * @param array $dbItems
     * @param array $dbErrors
     * @param array $dbWarnings
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @param array $previousDBItems
     * @param array $variables
     * @param array $messages
     * @return void
     */
    protected function addVariableValuesForResultItemInContext(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, $id, string $field, array &$resultIDItems, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        // Enable the query to provide variables to pass down
        $addVariables = $this->directiveArgsForSchema['addVariables'] ?? [];
        $appendVariables = $this->directiveArgsForSchema['appendVariables'] ?? [];
        if ($addVariables || $appendVariables) {
            // The variables may need `$value`, so add it
            $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
            $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
            $isValueInDBItems = array_key_exists($fieldOutputKey, $dbItems[(string)$id] ?? []);
            $dbKey = $dataloader->getDatabaseKey();
            $value = $isValueInDBItems ?
                $dbItems[(string)$id][$fieldOutputKey] :
                $previousDBItems[$dbKey][(string)$id][$fieldOutputKey];
            $this->addVariableValueForResultItem($id, 'value', $value, $messages);
            $resultItemVariables = $this->getVariablesForResultItem($id, $variables, $messages);

            $options = [
                AbstractFieldResolver::OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM => true,
            ];
            foreach ($addVariables as $key => $value) {
                // Evaluate the $value, since it may be a function
                if ($fieldQueryInterpreter->isFieldArgumentValueAField($value)) {
                    $value = $fieldResolver->resolveValue($resultIDItems[(string)$id], $value, $resultItemVariables, $options);
                }
                $this->addVariableValueForResultItem($id, $key, $value, $messages);
            }
            foreach ($appendVariables as $key => $value) {
                $existingValue = $this->getVariableValueForResultItem($id, $key, $messages) ?? [];
                // Evaluate the $value, since it may be a function
                if ($fieldQueryInterpreter->isFieldArgumentValueAField($value)) {
                    $existingValue[] = $fieldResolver->resolveValue($resultIDItems[(string)$id], $value, $resultItemVariables, $options);
                }
                $this->addVariableValueForResultItem($id, $key, $existingValue, $messages);
            }
        }
    }
}
