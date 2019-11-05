<?php
namespace PoP\ComponentModel\DirectiveResolvers;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\Schema\TypeCastingHelpers;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

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
                SchemaDefinition::ARGNAME_NAME => 'relationalFieldOutputKey',
                SchemaDefinition::ARGNAME_TYPE => SchemaDefinition::TYPE_STRING,
                SchemaDefinition::ARGNAME_DESCRIPTION => $translationAPI->__('The field that loads the relational object', 'component-model'),
                SchemaDefinition::ARGNAME_MANDATORY => true,
            ],
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
    public function resolveDirective(FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        // $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // // foreach ($idsDataFields as $id => $dataFields) {
        // //     foreach ($dataFields['direct'] as $field) {
        // //         $fieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($field);
        // //         $dbItems[(string)$id][$fieldOutputKey] = $dbItems[(string)$id][$fieldOutputKey];
        // //     }
        // // }
// var_dump('$idsDataFields', $idsDataFields);
        $relationalFieldOutputKey = $this->directiveArgsForSchema['relationalFieldOutputKey'];
        $copyFromFields = $this->directiveArgsForSchema['copyFromFields'];
        $copyFromFieldsCount = count($copyFromFields);
        if (isset($this->directiveArgsForSchema['copyToFields'])) {
            $copyToFields = $this->directiveArgsForSchema['copyToFields'];
            // Validate that both arrays have the same number of elements
            $copyToFieldsCount = count($copyToFields);
            if ($copyToFieldsCount > $copyFromFieldsCount) {
                $schemaWarnings[$this->directive][] = sprintf(
                    $translationAPI->__('Argument \'copyToFields\' has more elements than argument \'copyFromFields\'. The following fields from \'copyToFields\' have been ignored: \'%s\'', 'component-model'),
                    implode($translationAPI->__('\', \''), array_slice($copyToFields, $copyFromFieldsCount))
                );
            } elseif ($copyToFieldsCount < $copyFromFieldsCount) {
                $schemaWarnings[$this->directive][] = sprintf(
                    $translationAPI->__('Argument \'copyToFields\' has fewer elements than argument \'copyFromFields\'. The following fields from \'copyFromFields\' will be copied to the destination object under their same field name: \'%s\'', 'component-model'),
                    implode($translationAPI->__('\', \''), array_slice($copyFromFields, $copyToFieldsCount))
                );
            }
        } else {
            $copyToFields = $copyFromFields;
        }

        // Obtain the DBKey under which the relationalField is stored in the database
        // $relationalFieldDBKey = ...;
        // THIS IS TESTING CODE!!!!! MUST FIX HERE!!!
        $relationalFieldDBKey = 'posts';
        // Copy the data from each of the relational object fields to the current object
        for ($i=0; $i<$copyFromFieldsCount; $i++) {
            $copyFromField = $copyFromFields[$i];
            $copyToField = $copyToFields[$i] ?? $copyFromFields[$i];
            foreach (array_keys($idsDataFields) as $id) {
                // If the destination field already exists, warn that it will be overriden
                if (array_key_exists($copyToField, $dbItems[(string)$id] ?? [])) {
                    $dbWarnings[$this->directive][] = sprintf(
                        $translationAPI->__('Field \'%s\' for object with ID \'%s\' had already been set (with value \'%s\'), so it has been overriden', 'component-model'),
                        $copyToField,
                        $id,
                        $dbItems[(string)$id][$copyToField]
                    );
                }
                // THIS IS TESTING CODE!!!!! MUST FIX HERE!!!
                $dbItems[(string)$id][$copyToField] = 'sarola';
                // $dbItems[(string)$id][$copyToField] = $database[$relationalFieldDBKey][(string)$id][$copyFromField];
            }
        }
    }
}
