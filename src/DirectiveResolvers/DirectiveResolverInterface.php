<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DataloaderInterface;

interface DirectiveResolverInterface
{
    public static function getDirectiveName(): string;
    /**
     * Indicate to what fieldNames this directive can be applied.
     * Returning an empty array means all of them
     *
     * @return array
     */
    public static function getFieldNamesToApplyTo(): array;
    /**
     * Extract and validate the directive arguments
     *
     * @param FieldResolverInterface $fieldResolver
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function dissectAndValidateDirectiveForSchema(FieldResolverInterface $fieldResolver, array &$fieldDirectiveFields, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;

    /**
     * Enable the directiveResolver to validate the directive arguments in a custom way
     *
     * @param FieldResolverInterface $fieldResolver
     * @param array $directiveArgs
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function validateDirectiveArgumentsForSchema(FieldResolverInterface $fieldResolver, array $directiveArgs, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;
    /**
     * Define where to place the directive in the directive execution pipeline
     * 2 directives are mandatory: Validate and ResolveAndMerge, which are executed in this order.
     * All other directives must indicate where to position themselves, using these 2 directives as anchors.
     * There are 3 positions:
     * 1. At the beginning, before the Validate pipeline
     * 2. In the middle, between the Validate and Resolve directives
     * 3. At the end, after the ResolveAndMerge directive
     *
     * @return string
     */
    public function getPipelinePosition(): string;
    /**
     * Indicate if the directiveResolver can process the directive with the given name and args
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $directiveName
     * @param array $directiveArgs
     * @return boolean
     */
    public function resolveCanProcess(FieldResolverInterface $fieldResolver, string $directiveName, array $directiveArgs = []): bool;
    /**
     * Indicates if the directive can be added several times to the pipeline, or only once
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool;
    /**
     * Indicate if the directive needs to be passed $idsDataFields filled with data to be able to execute
     *
     * @return void
     */
    public function needsIDsDataFieldsToExecute(): bool;
    /**
     * Validate that the directive can be applied to all passed fields
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
    // public function validateDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
    /**
     * Get an instance of the object defining the schema for this fieldValueResolver
     *
     * @param FieldResolverInterface $fieldResolver
     * @param string $fieldName
     * @param array $fieldArgs
     * @return void
     */
    public function getSchemaDefinitionResolver(FieldResolverInterface $fieldResolver): ?SchemaDirectiveResolverInterface;
    public function getSchemaDefinitionForDirective(FieldResolverInterface $fieldResolver): array;
}
