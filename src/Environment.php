<?php
namespace PoP\ComponentModel;

class Environment
{
    /**
     * Indicate: If a directive fails, then remove the affected IDs/fields from the upcoming stages of the directive pipeline execution
     *
     * @return bool
     */
    public static function removeFieldIfDirectiveFailed(): bool
    {
        return isset($_ENV['REMOVE_FIELD_IF_DIRECTIVE_FAILED']) ? strtolower($_ENV['REMOVE_FIELD_IF_DIRECTIVE_FAILED']) == "true" : false;
    }

    /**
     * Indicate: If a directive fails, then stop execution of the directive pipeline altogether
     *
     * @return bool
     */
    public static function stopDirectivePipelineExecutionIfDirectiveFailed(): bool
    {
        return isset($_ENV['STOP_DIRECTIVE_PIPELINE_EXECUTION_IF_DIRECTIVE_FAILED']) ? strtolower($_ENV['STOP_DIRECTIVE_PIPELINE_EXECUTION_IF_DIRECTIVE_FAILED']) == "true" : false;
    }

    /**
     * Indicate: If a directive fails, then stop execution of the directive pipeline altogether
     *
     * @return bool
     */
    public static function namespaceTypesAndInterfaces(): bool
    {
        return isset($_ENV['NAMESPACE_TYPES_AND_INTERFACES']) ? strtolower($_ENV['NAMESPACE_TYPES_AND_INTERFACES']) == "true" : false;
    }

    /**
     * Indicate if to enable to restrict a field by version, using the same semantic versioning constraint rules used by Composer
     *
     * @see https://getcomposer.org/doc/articles/versions.md Composer's semver constraint rules
     * @return bool
     */
    public static function enableSemanticVersioningConstraintsForFields(): bool
    {
        return isset($_ENV['ENABLE_SEMANTIC_VERSIONING_CONSTRAINTS_FOR_FIELDS']) ? strtolower($_ENV['ENABLE_SEMANTIC_VERSIONING_CONSTRAINTS_FOR_FIELDS']) == "true" : false;
    }
}

