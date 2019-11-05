<?php
namespace PoP\ComponentModel\DirectivePipeline;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DataloaderInterface;

class DirectivePipelineUtils
{
    public static function convertArgumentsToPayload(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages): array
    {
        return [
            'dataloader' => &$dataloader,
            'fieldResolver' => &$fieldResolver,
            'directiveResultSet' => &$resultIDItems,
            'idsDataFields' => &$idsDataFields,
            'dbItems' => &$dbItems,
            'dbErrors' => &$dbErrors,
            'dbWarnings' => &$dbWarnings,
            'schemaErrors' => &$schemaErrors,
            'schemaWarnings' => &$schemaWarnings,
            'schemaDeprecations' => &$schemaDeprecations,
            'previousDBItems' => &$previousDBItems,
            'variables' => &$variables,
            'messages' => &$messages,
        ];
    }

    public static function extractArgumentsFromPayload(array $payload): array
    {
        return [
            &$payload['dataloader'],
            &$payload['fieldResolver'],
            &$payload['directiveResultSet'],
            &$payload['idsDataFields'],
            &$payload['dbItems'],
            &$payload['dbErrors'],
            &$payload['dbWarnings'],
            &$payload['schemaErrors'],
            &$payload['schemaWarnings'],
            &$payload['schemaDeprecations'],
            &$payload['previousDBItems'],
            &$payload['variables'],
            &$payload['messages'],
        ];
    }
}
