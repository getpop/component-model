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
        return $translationAPI->__('Transform the value of a property in the current object', 'component-model');
    }

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
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

        $function = $this->directiveArgsForSchema['function'];
        $parameter = $this->directiveArgsForSchema['parameter'];

        // Insert the value under the property name, or in first position
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $functionName = $fieldQueryInterpreter->getFieldName($function);
        $functionArgElems = $fieldQueryInterpreter->extractFieldArguments($fieldResolver, $function);
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
                $value = $isValueInDBItems ?
                    $dbItems[(string)$id][$fieldOutputKey] :
                    $previousDBItems[$dbKey][(string)$id][$fieldOutputKey];
                $resultItemFunctionArgElems = $functionArgElems;
                if ($parameter) {
                    $resultItemFunctionArgElems[$parameter] = $value;
                } else {
                    array_unshift($resultItemFunctionArgElems, $value);
                }

                // Regenerate the function
                $resultItemFunction = $fieldQueryInterpreter->getField($functionName, $resultItemFunctionArgElems);

                // Validate the new fieldArgs once again, to make sure the addition of the new parameter is right (eg: maybe the param name where to pass the function is wrong)
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
                        $translationAPI->__('Transformation of field \'%s\' on object with ID \'%s\' can\'t be executed due to previous errors', 'component-model'),
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
                        $translationAPI->__('Transformation of field \'%s\' on object with ID \'%s\' failed due to error: %s', 'component-model'),
                        $fieldOutputKey,
                        $id,
                        $error->getErrorMessage()
                    );
                    continue;
                }
                $dbItems[(string)$id][$fieldOutputKey] = $functionValue;
            }
        }
    }
}
