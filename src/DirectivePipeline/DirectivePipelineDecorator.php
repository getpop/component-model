<?php
namespace PoP\ComponentModel\DirectivePipeline;
use League\Pipeline\PipelineInterface;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

class DirectivePipelineDecorator
{
    private $pipeline;
    function __construct(PipelineInterface $pipeline)
    {
        $this->pipeline = $pipeline;
    }
    public function resolveDirectivePipeline(TypeResolverInterface $typeResolver, array &$pipelineIDsDataFields, array &$pipelineDirectiveResolverInstances, array &$resultIDItems, array &$unionDBKeyIDs, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$dbDeprecations, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $payload = $this->pipeline->process(
            DirectivePipelineUtils::convertArgumentsToPayload(
                $typeResolver,
                $pipelineIDsDataFields,
                $pipelineDirectiveResolverInstances,
                $resultIDItems,
                $unionDBKeyIDs,
                $dbItems,
                $previousDBItems,
                $variables,
                $messages,
                $dbErrors,
                $dbWarnings,
                $dbDeprecations,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations
            )
        );
        list(
            $typeResolver,
            $pipelineIDsDataFields,
            $pipelineDirectiveResolverInstances,
            $resultIDItems,
            $unionDBKeyIDs,
            $dbItems,
            $previousDBItems,
            $variables,
            $messages,
            $dbErrors,
            $dbWarnings,
            $dbDeprecations,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations
        ) = DirectivePipelineUtils::extractArgumentsFromPayload($payload);
    }
}
