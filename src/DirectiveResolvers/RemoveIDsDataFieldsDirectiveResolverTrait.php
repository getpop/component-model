<?php
namespace PoP\ComponentModel\DirectiveResolvers;

trait RemoveIDsDataFieldsDirectiveResolverTrait
{
    protected function removeIDsDataFields(array &$idsDataFieldsToRemove, array &$succeedingPipelineIDsDataFields)
    {
        // For each combination of ID and field, remove them from the upcoming pipeline stages
        foreach ($idsDataFieldsToRemove as $id => $dataFields) {
            foreach ($succeedingPipelineIDsDataFields as &$pipelineStageIDsDataFields) {
                $pipelineStageIDsDataFields[(string)$id]['direct'] = array_diff(
                    $pipelineStageIDsDataFields[(string)$id]['direct'],
                    $dataFields['direct']
                );
                foreach ($dataFields['direct'] as $removeField) {
                    unset($pipelineStageIDsDataFields[(string)$id]['conditional'][$removeField]);
                }
            }
        }
    }
}
