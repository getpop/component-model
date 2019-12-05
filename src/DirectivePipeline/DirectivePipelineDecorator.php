<?php
namespace PoP\ComponentModel\DirectivePipeline;
use League\Pipeline\PipelineInterface;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\TypeDataResolvers\TypeDataResolverInterface;

class DirectivePipelineDecorator
{
    private $pipeline;
    function __construct(PipelineInterface $pipeline)
    {
        $this->pipeline = $pipeline;
    }
    public function resolveDirectivePipeline(TypeDataResolverInterface $typeDataResolver, TypeResolverInterface $typeResolver, array &$pipelineIDsDataFields, array &$resultIDItems, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $payload = $this->pipeline->process(
            DirectivePipelineUtils::convertArgumentsToPayload(
                $typeDataResolver,
                $typeResolver,
                $pipelineIDsDataFields,
                $resultIDItems,
                $dbItems,
                $previousDBItems,
                $variables,
                $messages,
                $dbErrors,
                $dbWarnings,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations
            )
        );
        list(
            $typeDataResolver,
            $typeResolver,
            $pipelineIDsDataFields,
            $resultIDItems,
            $dbItems,
            $previousDBItems,
            $variables,
            $messages,
            $dbErrors,
            $dbWarnings,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations
        ) = DirectivePipelineUtils::extractArgumentsFromPayload($payload);
    }
}
