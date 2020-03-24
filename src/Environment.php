<?php
namespace PoP\ComponentModel;

class Environment
{
    public const ENABLE_SCHEMA_ENTITY_REGISTRIES = 'ENABLE_SCHEMA_ENTITY_REGISTRIES';

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

    /**
     * Indicate if to keep the several entities that make up a schema (types, directives) in a registry
     * This functionality is not used by PoP itself, hence it defaults to `false`
     * It can be used by making a mapping from type name to type resolver class, as to reference a type
     * by a name, if needed (eg: to save in the application's configuration)
     *
     * @return bool
     */
    public static function enableSchemaEntityRegistries(): bool
    {
        return isset($_ENV[self::ENABLE_SCHEMA_ENTITY_REGISTRIES]) ? strtolower($_ENV[self::ENABLE_SCHEMA_ENTITY_REGISTRIES]) == "true" : false;
    }
}

