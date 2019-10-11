<?php
namespace PoP\ComponentModel\DirectivePipeline;

class DirectivePipelineUtils
{
    public static function convertArgumentsToPayload($fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        return [
            'fieldResolver' => &$fieldResolver,
            'directiveResultSet' => &$resultIDItems,
            'idsDataFields' => &$idsDataFields,
            'dbItems' => &$dbItems,
            'dbErrors' => &$dbErrors,
            'dbWarnings' => &$dbWarnings,
            'schemaErrors' => &$schemaErrors,
            'schemaWarnings' => &$schemaWarnings,
            'schemaDeprecations' => &$schemaDeprecations
        ];
    }

    public static function extractArgumentsFromPayload(array $payload): array
    {
        return [
            &$payload['fieldResolver'],
            &$payload['directiveResultSet'],
            &$payload['idsDataFields'],
            &$payload['dbItems'],
            &$payload['dbErrors'],
            &$payload['dbWarnings'],
            &$payload['schemaErrors'],
            &$payload['schemaWarnings'],
            &$payload['schemaDeprecations'],
        ];
    }
}
