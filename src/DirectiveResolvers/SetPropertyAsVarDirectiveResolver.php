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
     * Validate that the number of elements in the fields `properties` and `variables` match one another
     *
     * @param FieldResolverInterface $fieldResolver
     * @param array $directiveArgs
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function validateDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, array $directiveArgs, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        $directiveArgs = parent::validateDirectiveArgumentsForSchema($fieldResolver, $directiveArgs, $schemaErrors, $schemaWarnings, $schemaDeprecations);
        $translationAPI = TranslationAPIFacade::getInstance();

        if (isset($directiveArgs['variables'])) {
            $variablesName = $directiveArgs['variables'];
            $properties = $directiveArgs['properties'];
            $variablesNameCount = count($variablesName);
            $propertiesCount = count($properties);

            // Validate that both arrays have the same number of elements
            if ($variablesNameCount > $propertiesCount) {
                $schemaWarnings[$this->directive][] = sprintf(
                    $translationAPI->__('Argument \'variables\' has more elements than argument \'properties\', so the following variables have been ignored: \'%s\'', 'component-model'),
                    implode($translationAPI->__('\', \''), array_slice($variablesName, $propertiesCount))
                );
            } elseif ($variablesNameCount < $propertiesCount) {
                $schemaWarnings[$this->directive][] = sprintf(
                    $translationAPI->__('Argument \'properties\' has more elements than argument \'variables\', so the following properties will be assigned to the destination object under their same name: \'%s\'', 'component-model'),
                    implode($translationAPI->__('\', \''), array_slice($properties, $variablesNameCount))
                );
            }
        }

        return $directiveArgs;
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
        $translationAPI = TranslationAPIFacade::getInstance();
        // Send a message to the resolveAndMerge directive, indicating which properties to retrieve
        $properties = $this->directiveArgsForSchema['properties'];
        $variableNames = $this->directiveArgsForSchema['variables'] ?? $properties;
        $dbKey = $dataloader->getDatabaseKey();
        foreach (array_keys($idsDataFields) as $id) {
            for ($i=0; $i<count($properties); $i++) {
                // Validate that the property exists in the source object, either on this iteration or any previous one
                $property = $properties[$i];
                $isValueInDBItems = array_key_exists($property, $dbItems[(string)$id] ?? []);
                if (!$isValueInDBItems && !array_key_exists($property, $previousDBItems[$dbKey][(string)$id] ?? [])) {
                    $dbErrors[(string)$id][$this->directive][] = sprintf(
                        $translationAPI->__('Property \'%s\' hadn\'t been set for object with ID \'%s\', so no variable has been defined', 'component-model'),
                        $property,
                        $id
                    );
                    continue;
                }
                // Check if the value already exists
                $variableName = $variableNames[$i];
                $existingValue = $this->getVariableValueForResultItem($id, $variableName, $messages);
                if (!is_null($existingValue)) {
                    $dbWarnings[(string)$id][$this->directive][] = sprintf(
                        $translationAPI->__('The existing value for variable \'%s\' for object with ID \'%s\' has been overriden: \'%s\'', 'component-model'),
                        $variableName,
                        $id
                    );
                }
                $value = $isValueInDBItems ? $dbItems[(string)$id][$property] : $previousDBItems[$dbKey][(string)$id][$property];
                $this->addVariableValueForResultItem($id, $variableName, $value, $messages);
            }
        }
    }
}
