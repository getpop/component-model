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
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $functionName = $fieldQueryInterpreter->getFieldName($function);
        $functionArgElems = $fieldQueryInterpreter->extractFieldArguments($fieldResolver, $function);
        $dbKey = $dataloader->getDatabaseKey();

        // Get the value from the object
        foreach (array_keys($idsDataFields) as $id) {
            // Validate that the property exists
            $isValueInDBItems = array_key_exists($property, $dbItems[(string)$id] ?? []);
            if (!$isValueInDBItems && !array_key_exists($property, $previousDBItems[$dbKey][(string)$id] ?? [])) {
                $dbErrors[(string)$id][$this->directive][] = sprintf(
                    $translationAPI->__('Property \'%s\' hadn\'t been set for object with ID \'%s\', so it can\'t be transformed', 'component-model'),
                    $property,
                    $id
                );
                continue;
            }
            $value = $isValueInDBItems ?
                $dbItems[(string)$id][$property] :
                $previousDBItems[$dbKey][(string)$id][$property];
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
            $resultItem = $resultIDItems[$id];
            // First for the Schema
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
                    $translationAPI->__('Transformation of property \'%s\' on object with ID \'%s\' can\'t be executed due to previous errors', 'component-model'),
                    $property,
                    $id
                );
                continue;
            }

            // Execute the function, and replace the value in the DB
            // We must indicate to validate the schema
            $options = [
                AbstractFieldResolver::OPTION_VALIDATE_SCHEMA_ON_RESULT_ITEM => true,
            ];
            $functionValue = $fieldResolver->resolveValue($resultIDItems[(string)$id], $resultItemFunction, $resultItemVariables, $options);
            if (GeneralUtils::isError($functionValue)) {
                $error = $functionValue;
                $dbErrors[(string)$id][$this->directive][] = sprintf(
                    $translationAPI->__('Transformation of property \'%s\' on object with ID \'%s\' failed due to error: %s', 'component-model'),
                    $property,
                    $id,
                    $error->getErrorMessage()
                );
                continue;
            }
            $dbItems[(string)$id][$target] = $functionValue;
        }
    }
}
