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
    public function resolveDirectivePipeline(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$previousDBItems, array &$variables, array &$messages, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $payload = $this->pipeline->process(
            DirectivePipelineUtils::convertArgumentsToPayload(
                $dataloader,
                $fieldResolver,
                $resultIDItems,
                $idsDataFields,
                $dbItems,
                $dbErrors,
                $dbWarnings,
                $schemaErrors,
                $schemaWarnings,
                $schemaDeprecations,
                $previousDBItems,
                $variables,
                $messages
            )
        );
        list(
            $dataloader,
            $fieldResolver,
            $resultIDItems,
            $idsDataFields,
            $dbItems,
            $dbErrors,
            $dbWarnings,
            $schemaErrors,
            $schemaWarnings,
            $schemaDeprecations,
            $previousDBItems,
            $variables,
            $messages
        ) = DirectivePipelineUtils::extractArgumentsFromPayload($payload);
    }
}
