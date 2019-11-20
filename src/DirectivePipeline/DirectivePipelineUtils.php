<?php
namespace PoP\ComponentModel\DirectivePipeline;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DataloaderInterface;

class DirectivePipelineUtils
{
    public static function convertArgumentsToPayload(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$pipelineIDsDataFields, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations): array
    {
        return [
            'dataloader' => &$dataloader,
            'fieldResolver' => &$fieldResolver,
            'pipelineIDsDataFields' => &$pipelineIDsDataFields,
            'resultIDItems' => &$resultIDItems,
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
            &$payload['dataloader'],
            &$payload['fieldResolver'],
            &$payload['pipelineIDsDataFields'],
            &$payload['resultIDItems'],
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
