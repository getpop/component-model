<?php
namespace PoP\ComponentModel\DirectiveResolvers;
use PoP\ComponentModel\DataloaderInterface;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\DirectiveResolvers\RemoveIDsDataFieldsDirectiveResolverTrait;
use PoP\ComponentModel\Feedback\Tokens;

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

    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $this->validateAndFilterFields($fieldResolver, $idsDataFields, $succeedingPipelineIDsDataFields, $variables, $schemaErrors, $schemaWarnings, $schemaDeprecations);
    }

    protected function validateAndFilterFields(FieldResolverInterface $fieldResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
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
        foreach ($dataFields as $field) {
            $this->validateField($fieldResolver, $field, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
        }
        // Remove from the data_fields list to execute on the resultItem for the next stages of the pipeline
        if ($failedDataFields) {
            $idsDataFieldsToRemove = [];
            foreach (array_keys($idsDataFields) as $id) {
                $idsDataFieldsToRemove[(string)$id]['direct'] = $failedDataFields;
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
        //         $this->validateAndFilterConditionalFields($fieldResolver, $conditionField, $idsDataFields[$id]['conditional'], $dataFields, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
        //     }
        // }
    }

    protected function validateField(FieldResolverInterface $fieldResolver, string $field, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables, array &$failedDataFields): bool {
        // Check for errors first, warnings and deprecations then
        $success = true;
        if ($schemaValidationErrors = $fieldResolver->resolveSchemaValidationErrorDescriptions($field, $variables)) {
            // foreach ($schemaValidationErrors as $schemaValidationError) {
            //     $schemaErrors[] = [
            //         Tokens::PATH => array_merge([$field], $schemaValidationError[Tokens::PATH]),
            //         Tokens::MESSAGE => $schemaValidationError[Tokens::MESSAGE],
            //     ];
            // }
            $schemaErrors = array_merge(
                $schemaErrors,
                $schemaValidationErrors
            );
            $failedDataFields[] = $field;
            $success = false;
        }
        if ($schemaValidationWarnings = $fieldResolver->resolveSchemaValidationWarningDescriptions($field, $variables)) {
            // foreach ($schemaValidationWarnings as $schemaValidationWarning) {
            //     $schemaWarnings[] = [
            //         Tokens::PATH => array_merge([$field], $schemaValidationWarning[Tokens::PATH]),
            //         Tokens::MESSAGE => $schemaValidationWarning[Tokens::MESSAGE],
            //     ];
            // }
            $schemaWarnings = array_merge(
                $schemaWarnings,
                $schemaValidationWarnings
            );
        }
        if ($schemaValidationDeprecations = $fieldResolver->getSchemaDeprecationDescriptions($field, $variables)) {
            // foreach ($schemaValidationDeprecations as $schemaValidationDeprecation) {
            //     $schemaDeprecations[] = [
            //         Tokens::PATH => array_merge([$field], $schemaValidationDeprecation[Tokens::PATH]),
            //         Tokens::MESSAGE => $schemaValidationDeprecation[Tokens::MESSAGE],
            //     ];
            // }
            $schemaDeprecations = array_merge(
                $schemaDeprecations,
                $schemaValidationDeprecations
            );
        }
        return $success;
    }

    // Since adding the Validate directive also when processing the conditional fields, there is no need to validate them now
    // They will be validated when it's their turn to be processed
    // protected function validateAndFilterConditionalFields(FieldResolverInterface $fieldResolver, string $conditionField, array &$rootConditionFields, array &$validatedFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$variables, array &$failedDataFields) {
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
    //         $validationResult = $this->validateField($fieldResolver, $conditionField, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
    //         // If the field has failed, then remove item from the root (so it's not fetched from the DB), and stop iterating
    //         if (!$validationResult) {
    //             unset($rootConditionFields[$conditionField]);
    //             return;
    //         }
    //     }
    //     // Repeat the process for all conditional fields
    //     foreach ($conditionalDataFields as $conditionalDataField => $entries) {
    //         $this->validateAndFilterConditionalFields($fieldResolver, $conditionalDataField, $rootConditionFields[$conditionField], $validatedFields, $schemaErrors, $schemaWarnings, $schemaDeprecations, $variables, $failedDataFields);
    //     }
    // }

    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return $translationAPI->__('It validates the field, filtering out those field arguments that raised a warning, or directly invalidating the field if any field argument raised an error. For instance, if a mandatory field argument is not provided, then it is an error and the field is invalidated and removed from the output; if a field argument can\'t be casted to its intended type, then it is a warning, the affected field argument is removed and the field is executed without it. This directive is already included by the engine, since its execution is mandatory', 'component-model');
    }
}
