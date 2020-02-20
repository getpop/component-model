<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\TypeResolvers\PipelinePositions;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\DirectiveResolvers\RemoveIDsDataFieldsDirectiveResolverTrait;

class ValidateDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    use RemoveIDsDataFieldsDirectiveResolverTrait;

    const DIRECTIVE_NAME = 'validate';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    /**
     * This directive must be the first one of the group at the middle
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::MIDDLE;
    }

    /**
     * Validating the directive can be done only once (and it is mandatory!)
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool
    {
        return false;
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

    protected function validateFields(TypeResolverInterface $typeResolver, array $dataFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables, array &$failedDataFields): void
    {
        foreach ($dataFields as $field) {
            $success = $this->validateField($typeResolver, $field, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables);
            if (!$success) {
                $failedDataFields[] = $field;
            }
        }
    }

    protected function validateField(TypeResolverInterface $typeResolver, string $field, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables): bool
    {
        // Check for errors first, warnings and deprecations then
        $success = true;
        if ($schemaValidationErrors = $typeResolver->resolveSchemaValidationErrorDescriptions($field, $variables)) {
            $schemaErrors = array_merge(
                $schemaErrors,
                $schemaValidationErrors
            );
            $success = false;
        }
        if ($schemaValidationWarnings = $typeResolver->resolveSchemaValidationWarningDescriptions($field, $variables)) {
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $schemaValidationWarnings
            );
        }
        if ($schemaValidationDeprecations = $typeResolver->resolveSchemaDeprecationDescriptions($field, $variables)) {
            $schemaDeprecations = array_merge(
                $schemaDeprecations,
                $schemaValidationDeprecations
            );
        }
        return $success;
    }

    // Since adding the Validate directive also when processing the conditional fields, there is no need to validate them now
    // They will be validated when it's their turn to be processed
    // protected function validateAndFilterConditionalFields(TypeResolverInterface $typeResolver, string $conditionField, array &$rootConditionFields, array &$validatedFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables, array &$failedDataFields) {
    //     // The root has key conditionField, and value conditionalFields
    //     // Check if the conditionField is valid. If it is not, remove from the root object
    //     // This will work because at the leaves we have empty arrays, so every data-field is a conditionField
    //     $conditionalDataFields = $rootConditionFields[$conditionField];
    //     // If this field has already been validated and failed, simply stop iterating from here on
    //     if (in_array($conditionField, $failedDataFields)) {
    //         return;
    //     }
    //     // If this field has been already validated, then don't validate again
    //     if (!in_array($conditionField, $validatedFields)) {
    //         $validatedFields[] = $conditionField;
    //         $validationResult = $this->validateField($typeResolver, $conditionField, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
    //         // If the field has failed, then remove item from the root (so it's not fetched from the DB), and stop iterating
    //         if (!$validationResult) {
    //             unset($rootConditionFields[$conditionField]);
    //             return;
    //         }
    //     }
    //     // Repeat the process for all conditional fields
    //     foreach ($conditionalDataFields as $conditionalDataField => $entries) {
    //         $this->validateAndFilterConditionalFields($typeResolver, $conditionalDataField, $rootConditionFields[$conditionField], $validatedFields, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
    //     }
    // }

    public function getSchemaDirectiveDescription(TypeResolverInterface $typeResolver): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return $translationAPI->__('It validates the field, filtering out those field arguments that raised a warning, or directly invalidating the field if any field argument raised an error. For instance, if a mandatory field argument is not provided, then it is an error and the field is invalidated and removed from the output; if a field argument can\'t be casted to its intended type, then it is a warning, the affected field argument is removed and the field is executed without it. This directive is already included by the engine, since its execution is mandatory', 'component-model');
    }
}
