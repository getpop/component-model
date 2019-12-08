<?php
namespace PoP\ComponentModel\DirectivePipeline;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\TypeDataResolvers\TypeDataResolverInterface;

class DirectivePipelineUtils
{
    public static function convertArgumentsToPayload(TypeDataResolverInterface $typeDataResolver, TypeResolverInterface $typeResolver, array &$pipelineIDsDataFields, array &$resultIDItems, array &$convertibleDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        return [
            'typeDataResolver' => &$typeDataResolver,
            'typeResolver' => &$typeResolver,
            'pipelineIDsDataFields' => &$pipelineIDsDataFields,
            'resultIDItems' => &$resultIDItems,
            'convertibleDBKeyIDs' => &$convertibleDBKeyIDs,
            'dbItems' => &$dbItems,
            'previousDBItems' => &$previousDBItems,
            'variables' => &$variables,
            'messages' => &$messages,
            'dbErrors' => &$dbErrors,
            'dbWarnings' => &$dbWarnings,
            'schemaErrors' => &$schemaErrors,
            'schemaWarnings' => &$schemaWarnings,
            'schemaDeprecations' => &$schemaDeprecations,
        ];
    }

    public static function extractArgumentsFromPayload(array $payload): array
    {
        return [
            &$payload['typeDataResolver'],
            &$payload['typeResolver'],
            &$payload['pipelineIDsDataFields'],
            &$payload['resultIDItems'],
            &$payload['convertibleDBKeyIDs'],
            &$payload['dbItems'],
            &$payload['previousDBItems'],
            &$payload['variables'],
            &$payload['messages'],
            &$payload['dbErrors'],
            &$payload['dbWarnings'],
            &$payload['schemaErrors'],
            &$payload['schemaWarnings'],
            &$payload['schemaDeprecations'],
        ];
    }
}
