<?php
namespace PoP\ComponentModel;

class Environment
{
    /**
     * Indicate: If a directive fails, then remove the affected IDs/fields from the upcoming stages of the directive pipeline execution
     *
     * @return void
     */
    public static function removeFieldIfDirectiveFailed(): bool
    {
        return isset($_ENV['REMOVE_FIELD_IF_DIRECTIVE_FAILED']) ? strtolower($_ENV['REMOVE_FIELD_IF_DIRECTIVE_FAILED']) == "true" : false;
    }

    /**
     * Indicate: If a directive fails, then stop execution of the directive pipeline altogether
     *
     * @return void
     */
    public static function stopDirectivePipelineExecutionIfDirectiveFailed(): bool
    {
        return isset($_ENV['STOP_DIRECTIVE_PIPELINE_EXECUTION_IF_DIRECTIVE_FAILED']) ? strtolower($_ENV['STOP_DIRECTIVE_PIPELINE_EXECUTION_IF_DIRECTIVE_FAILED']) == "true" : false;
    }
}

