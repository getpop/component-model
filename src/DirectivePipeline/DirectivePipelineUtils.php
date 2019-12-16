<?php
namespace PoP\ComponentModel\DirectivePipeline;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

class DirectivePipelineUtils
{
    public static function convertArgumentsToPayload(TypeResolverInterface $typeResolver, array &$pipelineIDsDataFields, array &$resultIDItems, array &$unionDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        return [
            'typeResolver' => &$typeResolver,
            'pipelineIDsDataFields' => &$pipelineIDsDataFields,
            'resultIDItems' => &$resultIDItems,
            'unionDBKeyIDs' => &$unionDBKeyIDs,
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
            &$payload['typeResolver'],
            &$payload['pipelineIDsDataFields'],
            &$payload['resultIDItems'],
            &$payload['unionDBKeyIDs'],
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
