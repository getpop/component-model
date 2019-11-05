<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\DataloaderInterface;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Schema\TypeCastingHelpers;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class CopyRelationalResultsDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    const DIRECTIVE_NAME = 'copyRelationalResults';
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
        return $translationAPI->__('Copy the data from a relational object (which is one level below) to the current object', 'component-model');
    }

    public function getSchemaDirectiveArgs(FieldResolverInterface $fieldResolver): array
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return [
            [
                SchemaDefinition::ARGNAME_NAME => 'copyFromFields',
                SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_STRING),
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The fields in the relational object from which to copy the data', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
            [
                SchemaDefinition::ARGNAME_NAME => 'copyToFields',
                SchemaDefinition::ARGNAME_TYPE => TypeCastingHelpers::combineTypes(SchemaDefinition::TYPE_ARRAY, SchemaDefinition::TYPE_STRING),
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The fields in the current object to which copy the data. If not provided, the same fields from the \'copyFromFields\' argument are used', 'component-model'),
            ],
        ];
    }

    /**
     * Validate that the number of elements in the fields `copyToFields` and `copyFromFields` match one another
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

        if (isset($directiveArgs['copyToFields'])) {
            $copyToFields = $directiveArgs['copyToFields'];
            $copyFromFields = $directiveArgs['copyFromFields'];
            $copyToFieldsCount = count($copyToFields);
            $copyFromFieldsCount = count($copyFromFields);

            // Validate that both arrays have the same number of elements
            if ($copyToFieldsCount > $copyFromFieldsCount) {
                $schemaWarnings[$this->directive][] = sprintf(
                    $translationAPI->__('Argument \'copyToFields\' has more elements than argument \'copyFromFields\', so the following fields have been ignored: \'%s\'', 'component-model'),
                    implode($translationAPI->__('\', \''), array_slice($copyToFields, $copyFromFieldsCount))
                );
            } elseif ($copyToFieldsCount < $copyFromFieldsCount) {
                $schemaWarnings[$this->directive][] = sprintf(
                    $translationAPI->__('Argument \'copyFromFields\' has more elements than argument \'copyToFields\', so the following fields will be copied to the destination object under their same field name: \'%s\'', 'component-model'),
                    implode($translationAPI->__('\', \''), array_slice($copyFromFields, $copyToFieldsCount))
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
        $instanceManager = InstanceManagerFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        $copyFromFields = $this->directiveArgsForSchema['copyFromFields'];
        $copyToFields = $this->directiveArgsForSchema['copyToFields'] ?? $copyFromFields;

        // From the dataloader, obtain under what dbKey the data for the current object is stored
        $dbKey = $dataloader->getDatabaseKey();

        // Copy the data from each of the relational object fields to the current object
        for ($i=0; $i<count($copyFromFields); $i++) {
            $copyFromField = $copyFromFields[$i];
            $copyToField = $copyToFields[$i] ?? $copyFromFields[$i];
            foreach ($idsDataFields as $id => $dataFields) {
                foreach ($dataFields['direct'] as $relationalField) {
                    // The data is stored under the field's output key
                    $relationalFieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($relationalField);
                    // Obtain the DBKey under which the relationalField is stored in the database
                    // For that, from the fieldResolver we obtain the dataloader for the `relationalField`
                    $relationalDataloaderClass = $fieldResolver->resolveFieldDefaultDataloaderClass($relationalField);
                    $relationalDataloader = $instanceManager->getInstance($relationalDataloaderClass);
                    $relationalDBKey = $relationalDataloader->getDatabaseKey();
                    // Validate that the current object has `relationalField` property set
                    if (!array_key_exists($relationalFieldOutputKey, $previousDBItems[$dbKey][(string)$id] ?? [])) {
                        if ($relationalFieldOutputKey != $relationalField) {
                            $dbWarnings[$this->directive][] = sprintf(
                                $translationAPI->__('Field \'%s\' (with output key \'%s\') for object with ID \'%s\' had not been set, so no data can be copied', 'component-model'),
                                $relationalField,
                                $relationalFieldOutputKey,
                                $id
                            );
                        } else {
                            $dbWarnings[$this->directive][] = sprintf(
                                $translationAPI->__('Field \'%s\' for object with ID \'%s\' had not been set, so no data can be copied', 'component-model'),
                                $relationalField,
                                $id
                            );
                        }
                        continue;
                    }

                    // If the destination field already exists, warn that it will be overriden
                    if (array_key_exists($copyToField, $dbItems[(string)$id] ?? [])) {
                        $dbWarnings[$this->directive][] = sprintf(
                            $translationAPI->__('Field \'%s\' for object with ID \'%s\' had already been set (with value \'%s\'), so it has been overriden', 'component-model'),
                            $copyToField,
                            $id,
                            $dbItems[(string)$id][$copyToField]
                        );
                    }
                    // Copy the properties into the array
                    $dbItems[(string)$id][$copyToField] = [];
                    $relationalIDs = $previousDBItems[$dbKey][(string)$id][$relationalFieldOutputKey];
                    foreach ($relationalIDs as $relationalID) {
                        $dbItems[(string)$id][$copyToField][] = $previousDBItems[$relationalDBKey][(string)$relationalID][$copyFromField];
                    }
                }
            }
        }
    }
}
