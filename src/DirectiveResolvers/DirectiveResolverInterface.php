<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\TypeDataLoaders\TypeDataLoaderInterface;

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
     * @param TypeResolverInterface $typeResolver
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function dissectAndValidateDirectiveForSchema(TypeResolverInterface $typeResolver, array &$fieldDirectiveFields, array &$variables, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;

    /**
     * Enable the directiveResolver to validate the directive arguments in a custom way
     *
     * @param TypeResolverInterface $typeResolver
     * @param array $directiveArgs
     * @param array $schemaErrors
     * @param array $schemaWarnings
     * @param array $schemaDeprecations
     * @return array
     */
    public function validateDirectiveArgumentsForSchema(TypeResolverInterface $typeResolver, array $directiveArgs, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array;
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
     * @param TypeResolverInterface $typeResolver
     * @param string $directiveName
     * @param array $directiveArgs
     * @param string $field
     * @return boolean
     */
    public function resolveCanProcess(TypeResolverInterface $typeResolver, string $directiveName, array $directiveArgs = [], string $field, array &$variables): bool;
    /**
     * Indicates if the directive can be added several times to the pipeline, or only once
     *
     * @return boolean
     */
    public function canExecuteMultipleTimesInField(): bool;
    // /**
    //  * Indicate if the directive needs to be passed $idsDataFields filled with data to be able to execute
    //  *
    //  * @return void
    //  */
    // public function needsIDsDataFieldsToExecute(): bool;
    /**
     * Validate that the directive can be applied to all passed fields
     *
     * @param TypeResolverInterface $typeResolver
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
    // public function validateDirective(TypeDataLoaderInterface $typeDataResolver, TypeResolverInterface $typeResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
    public function resolveDirective(TypeDataLoaderInterface $typeDataResolver, TypeResolverInterface $typeResolver, array &$idsDataFields, array &$succeedingPipelineIDsDataFields, array &$resultIDItems, array &$convertibleDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations);
    /**
     * Get an instance of the object defining the schema for this fieldResolver
     *
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @param array $fieldArgs
     * @return void
     */
    public function getSchemaDefinitionResolver(TypeResolverInterface $typeResolver): ?SchemaDirectiveResolverInterface;
    /**
     * A directive can decide to not be added to the schema, eg: when it is repeated/implemented several times
     *
     * @return void
     */
    public function skipAddingToSchemaDefinition();
    public function getSchemaDefinitionForDirective(TypeResolverInterface $typeResolver): array;
}
