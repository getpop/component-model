<?php
namespace PoP\ComponentModel\DirectiveResolvers;
use League\Pipeline\StageInterface;
use PoP\ComponentModel\Environment;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DirectivePipeline\DirectivePipelineUtils;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;

abstract class AbstractDirectiveResolver implements DirectiveResolverInterface, StageInterface
{
    use AttachableExtensionTrait, DirectiveValidatorTrait;

    protected $directive;
    function __construct($directive) {
        $this->directive = $directive;
    }

    public static function getClassesToAttachTo(): array
    {
        // By default, be attached to all fieldResolvers
        return [
            AbstractFieldResolver::class,
        ];
    }

    /**
     * Indicate to what fieldNames this directive can be applied.
     * Returning an empty array means all of them
     *
     * @return array
     */
    public static function getFieldNamesToApplyTo(): array
    {
        // By default, apply to all fieldNames
        return [];
    }

    /**
     * By default, place the directive between Validate and ResolveAndMerge directives
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::MIDDLE;
    }

    /**
     * By default, a directive can be executed only once in the field (i.e. placed only once in the directive pipeline)
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool
    {
        return false;
    }

    public function __invoke($payload)
    {
        // 1. Extract the arguments from the payload
        list(
            $fieldResolver,
            $resultIDItems,
            $idsDataFields,
            $dbItems,
            $dbErrors,
            $dbWarnings,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations
        ) = DirectivePipelineUtils::extractArgumentsFromPayload($payload);

        // 2. Validate operation
        $this->validateDirective(
            $fieldResolver,
            $resultIDItems,
            $idsDataFields,
            $dbItems,
            $dbErrors,
            $dbWarnings,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations
        );

        // 3. Execute operation
        $this->resolveDirective(
            $fieldResolver,
            $resultIDItems,
            $idsDataFields,
            $dbItems,
            $dbErrors,
            $dbWarnings,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations
        );

        // 4. Re-create the payload from the modified variables
        return DirectivePipelineUtils::convertArgumentsToPayload(
            $fieldResolver,
            $resultIDItems,
            $idsDataFields,
            $dbItems,
            $dbErrors,
            $dbWarnings,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations
        );
    }

    public function validateDirective(FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        // Check that the directive can be applied to all provided fields
        $this->validateAndFilterFieldsForDirective($idsDataFields, $schemaErrors, $schemaWarnings);
    }

    /**
     * Check that the directive can be applied to all provided fields
     *
     * @param array $idsDataFields
     * @param array $schemaErrors
     * @return void
     */
    protected function validateAndFilterFieldsForDirective(array &$idsDataFields, array &$schemaErrors, array &$schemaWarnings)
    {
        $directiveSupportedFieldNames = $this->getFieldNamesToApplyTo();

        // If this function returns an empty array, then it supports all fields, then do nothing
        if (!$directiveSupportedFieldNames) {
            return;
        }

        // Check if all fields are supported by this directive
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $failedFields = [];
        foreach ($idsDataFields as $id => &$data_fields) {
            // Get the fieldName for each field
            $nameFields = [];
            foreach ($data_fields['direct'] as $field) {
                $nameFields[$fieldQueryInterpreter->getFieldName($field)] = $field;
            }
            // If any fieldName failed, remove it from the list of fields to execute for this directive
            if ($unsupportedFieldNames = array_diff(array_keys($nameFields), $directiveSupportedFieldNames)) {
                $unsupportedFields = array_map(
                    function($fieldName) use ($nameFields) {
                        return $nameFields[$fieldName];
                    },
                    $unsupportedFieldNames
                );
                $failedFields = array_values(array_unique(array_merge(
                    $failedFields,
                    $unsupportedFields
                )));
            }
        }
        // Give an error message for all failed fields
        if ($failedFields) {
            $translationAPI = TranslationAPIFacade::getInstance();
            $directiveName = $this->getDirectiveName();
            $failedFieldNames = array_map(
                [$fieldQueryInterpreter, 'getFieldName'],
                $failedFields
            );
            if (count($failedFields) == 1) {
                $message = $translationAPI->__('Directive \'%s\' doesn\'t support field \'%s\' (the only supported field names are: \'%s\')', 'component-model');
            } else {
                $message = $translationAPI->__('Directive \'%s\' doesn\'t support fields \'%s\' (the only supported field names are: \'%s\')', 'component-model');
            }
            $failureMessage = sprintf(
                $message,
                $directiveName,
                implode($translationAPI->__('\', \''), $failedFieldNames),
                implode($translationAPI->__('\', \''), $directiveSupportedFieldNames)
            );
            $this->processFailure($failureMessage, $failedFields, $idsDataFields, $schemaErrors, $schemaWarnings);
        }
    }


    /**
     * Depending on environment configuration, either show a warning, or show an error and remove the fields from the directive pipeline for further execution
     *
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @return void
     */
    protected function processFailure(string $failureMessage, array $failedFields = [], array &$idsDataFields, array &$schemaErrors, array &$schemaWarnings)
    {
        // If the failure must be processed as an error, we must also remove the fields from the directive pipeline
        $removeFieldIfDirectiveFailed = Environment::removeFieldIfDirectiveFailed();
        if ($removeFieldIfDirectiveFailed) {
            // If $failedFields is empty, it means all fields failed
            $allFieldsFailed = empty($failedFields);
            foreach ($idsDataFields as $id => &$data_fields) {
                if ($allFieldsFailed) {
                    // Calculate which fields are being removed, to add to the error
                    $failedFields = array_merge(
                        $failedFields,
                        $data_fields['direct']
                    );
                    $data_fields['direct'] = [];
                } else {
                    $data_fields['direct'] = array_diff(
                        $data_fields['direct'],
                        $failedFields
                    );
                }
            }
            $failedFields = array_values(array_unique($failedFields));
        }
        // Show the failureMessage either as error or as warning
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        $translationAPI = TranslationAPIFacade::getInstance();
        $directiveName = $this->getDirectiveName();
        $failedFieldNames = array_map(
            [$fieldQueryInterpreter, 'getFieldName'],
            $failedFields
        );
        if ($removeFieldIfDirectiveFailed) {
            if (count($failedFieldNames) == 1) {
                $message = $translationAPI->__('%s. Field \'%s\' has been removed from the directive pipeline', 'component-model');
            } else {
                $message = $translationAPI->__('%s. Fields \'%s\' have been removed from the directive pipeline', 'component-model');
            }
            $schemaErrors[$directiveName][] = sprintf(
                $message,
                $failureMessage,
                implode($translationAPI->__('\', \''), $failedFieldNames)
            );
        } else {
            if (count($failedFieldNames) == 1) {
                $message = $translationAPI->__('%s. Execution of directive \'%s\' has been ignored on field \'%s\'', 'component-model');
            } else {
                $message = $translationAPI->__('%s. Execution of directive \'%s\' has been ignored on fields \'%s\'', 'component-model');
            }
            $schemaWarnings[$directiveName][] = sprintf(
                $message,
                $failureMessage,
                $directiveName,
                implode($translationAPI->__('\', \''), $failedFieldNames)
            );
        }
    }
}
