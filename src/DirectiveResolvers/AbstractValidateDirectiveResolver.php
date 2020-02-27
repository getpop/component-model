<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\TypeResolvers\PipelinePositions;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\RemoveIDsDataFieldsDirectiveResolverTrait;

abstract class AbstractValidateDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    use RemoveIDsDataFieldsDirectiveResolverTrait;

    /**
     * This directive must be the first one of the group at the middle
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::MIDDLE;
    }

    public function resolveDirective(TypeResolverInterface $typeResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$resultIDItems, array &$unionDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$dbDeprecations, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $this->validateAndFilterFields($typeResolver, $idsDataFields, $succeedingPipelineIDsDataFields, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);
    }

    protected function validateAndFilterFields(TypeResolverInterface $typeResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        // Validate that the schema and the provided data match, eg: passing mandatory values
        // (Such as fieldArg "status" for field "is-status")
        // Combine all the datafields under all IDs
        $dataFields = $failedDataFields = [];
        foreach ($idsDataFields as $id => $data_fields) {
            $dataFields = array_values(array_unique(array_merge(
                $dataFields,
                $data_fields['direct']
            )));
        }
        $this->validateFields($typeResolver, $dataFields, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);

        // Remove from the data_fields list to execute on the resultItem for the next stages of the pipeline
        if ($failedDataFields) {
            $idsDataFieldsToRemove = [];
            foreach ($idsDataFields as $id => $dataFields) {
                $idsDataFieldsToRemove[(string)$id]['direct'] = array_intersect(
                    $dataFields['direct'],
                    $failedDataFields
                );
            }
            $this->removeIDsDataFields($idsDataFieldsToRemove, $succeedingPipelineIDsDataFields);
        }
        // Since adding the Validate directive also when processing the conditional fields, there is no need to validate them now
        // They will be validated when it's their turn to be processed
        // // Validate conditional fields and, if they fail, already take them out from the `$idsDataFields` object
        // $dataFields = $failedDataFields = [];
        // // Because on the leaves we encounter an empty array, all fields are conditional fields (even if they are on the leaves)
        // foreach ($idsDataFields as $id => $data_fields) {
        //     foreach ($data_fields['conditional'] as $conditionField => $conditionalFields) {
        //         $this->validateAndFilterConditionalFields($typeResolver, $conditionField, $idsDataFields[$id]['conditional'], $dataFields, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
        //     }
        // }
    }

    abstract protected function validateFields(TypeResolverInterface $typeResolver, array $dataFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables, array &$failedDataFields): void;
}
