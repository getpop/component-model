<?php
namespace PoP\ComponentModel\DirectivePipeline;
use League\Pipeline\PipelineInterface;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\DataloaderInterface;

class DirectivePipelineDecorator
{
    private $pipeline;
    function __construct(PipelineInterface $pipeline)
    {
        $this->pipeline = $pipeline;
    }
    public function resolveDirectivePipeline(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$pipelineIDsDataFields, array &$resultIDItems/*$pipelineResultIDItems*/, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $payload = $this->pipeline->process(
            DirectivePipelineUtils::convertArgumentsToPayload(
                $dataloader,
                $fieldResolver,
                $pipelineIDsDataFields,
                $resultIDItems,//$pipelineResultIDItems,
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
            $dataloader,
            $fieldResolver,
            $pipelineIDsDataFields,
            $resultIDItems,//$pipelineResultIDItems,
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
