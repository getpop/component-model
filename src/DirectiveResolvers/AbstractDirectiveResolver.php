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
        $removeFieldIfDirectiveFailed = Environment::removeFieldIfDirectiveFailed();
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
                if ($removeFieldIfDirectiveFailed) {
                    $data_fields['direct'] = array_diff(
                        $data_fields['direct'],
                        $unsupportedFields
                    );
                }
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
            if ($removeFieldIfDirectiveFailed) {
                if (count($failedFieldNames) == 1) {
                    $message = $translationAPI->__('Directive \'%s\' doesn\'t support field \'%s\', so it has been removed from the query. (The only supported field names are: \'%s\')', 'component-model');
                } else {
                    $message = $translationAPI->__('Directive \'%s\' doesn\'t support fields \'%s\', so these have been removed from the query. (The only supported field names are: \'%s\')', 'component-model');
                }
                $schemaErrors[$directiveName][] = sprintf(
                    $message,
                    $directiveName,
                    implode($translationAPI->__('\', \''), $failedFieldNames),
                    implode($translationAPI->__('\', \''), $directiveSupportedFieldNames)
                );
            } else {
                if (count($failedFieldNames) == 1) {
                    $message = $translationAPI->__('Directive \'%s\' doesn\'t support field \'%s\', so execution of this directive has been ignored on this field. (The only supported field names are: \'%s\')', 'component-model');
                } else {
                    $message = $translationAPI->__('Directive \'%s\' doesn\'t support fields \'%s\', so execution of this directive has been ignored on them. (The only supported field names are: \'%s\')', 'component-model');
                }
                $schemaWarnings[$directiveName][] = sprintf(
                    $message,
                    $directiveName,
                    implode($translationAPI->__('\', \''), $failedFieldNames),
                    implode($translationAPI->__('\', \''), $directiveSupportedFieldNames)
                );
            }
        }
    }
}
