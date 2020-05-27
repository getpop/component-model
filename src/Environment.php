<?php

declare(strict_types=1);

namespace PoP\ComponentModel;

class Environment
{
    public const ENABLE_SCHEMA_ENTITY_REGISTRIES = 'ENABLE_SCHEMA_ENTITY_REGISTRIES';
    public const USE_COMPONENT_MODEL_CACHE = 'USE_COMPONENT_MODEL_CACHE';
    public const ENABLE_CONFIG_BY_PARAMS = 'ENABLE_CONFIG_BY_PARAMS';
    public const NAMESPACE_TYPES_AND_INTERFACES = 'NAMESPACE_TYPES_AND_INTERFACES';

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
     * Indicate if to enable to restrict a field and directive by version,
     * using the same semantic versioning constraint rules used by Composer
     *
     * @see https://getcomposer.org/doc/articles/versions.md Composer's semver constraint rules
     * @return bool
     */
    public static function enableSemanticVersionConstraints(): bool
    {
        return isset($_ENV['ENABLE_SEMANTIC_VERSION_CONSTRAINTS']) ? strtolower($_ENV['ENABLE_SEMANTIC_VERSION_CONSTRAINTS']) == "true" : false;
    }
}
