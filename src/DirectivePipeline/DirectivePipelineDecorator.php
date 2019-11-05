<?php
namespace PoP\ComponentModel\DirectivePipeline;
use League\Pipeline\PipelineInterface;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

class DirectivePipelineDecorator
{
    private $pipeline;
    function __construct(PipelineInterface $pipeline)
    {
        $this->pipeline = $pipeline;
    }
    public function resolveDirectivePipeline(FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $payload = $this->pipeline->process(
            DirectivePipelineUtils::convertArgumentsToPayload(
                $fieldResolver,
                $resultIDItems,
                $idsDataFields,
                $dbItems,
                $dbErrors,
                $dbWarnings,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations
            )
        );
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
    }
}
