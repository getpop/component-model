<?php
namespace PoP\ComponentModel;

use PoP\ComponentModel\AbstractComponentConfiguration;

class ComponentConfiguration extends AbstractComponentConfiguration
{
    private static $enableSchemaEntityRegistries;

    /**
     * Access layer to the environment variable, enabling to override its value
     * Indicate if to keep the several entities that make up a schema (types, directives) in a registry
     * This functionality is not used by PoP itself, hence it defaults to `false`
     * It can be used by making a mapping from type name to type resolver class, as to reference a type
     * by a name, if needed (eg: to save in the application's configuration)
     *
     * @return bool
     */
    public static function enableSchemaEntityRegistries(): bool
    {
        // Define properties
        $envVariable = Environment::ENABLE_SCHEMA_ENTITY_REGISTRIES;
        $selfProperty = &self::$enableSchemaEntityRegistries;
        $callback = [Environment::class, 'enableSchemaEntityRegistries'];

        // Initialize property from the environment/hook
        self::maybeInitEnvironmentVariable(
            $envVariable,
            $selfProperty,
            $callback
        );
        return $selfProperty;
    }
}

