<?php

declare(strict_types=1);

namespace PoP\ComponentModel;

use PoP\ComponentModel\ComponentConfiguration\AbstractComponentConfiguration;

class ComponentConfiguration extends AbstractComponentConfiguration
{
    /**
     * Map with the configuration passed by params
     *
     * @var array
     */
    private static $overrideConfiguration;

    private static $enableConfigByParams;
    private static $useComponentModelCache;
    private static $enableSchemaEntityRegistries;
    private static $namespaceTypesAndInterfaces;

    /**
     * Initialize component configuration
     *
     * @return void
     */
    public static function init(): void
    {
        // Allow to override the configuration with values passed in the query string:
        // "config": comma-separated string with all fields with value "true"
        // Whatever fields are not there, will be considered "false"
        self::$overrideConfiguration = array();
        if (self::enableConfigByParams()) {
            self::$overrideConfiguration = $_REQUEST[\POP_URLPARAM_CONFIG] ? explode(\POP_CONSTANT_PARAMVALUE_SEPARATOR, $_REQUEST[\POP_URLPARAM_CONFIG]) : array();
        }
    }

    /**
     * Indicate if the configuration is overriden by params
     *
     * @return boolean
     */
    public static function doingOverrideConfiguration(): bool
    {
        return !empty(self::$overrideConfiguration);
    }

    /**
     * Obtain the override configuration for a key, with possible values being only
     * `true` or `false`, or `null` if that key is not set
     *
     * @param $key the key to get the value
     */
    public static function getOverrideConfiguration(string $key): ?bool
    {
        // If no values where defined in the configuration, then skip it completely
        if (empty(self::$overrideConfiguration)) {
            return null;
        }

        // Check if the key has been given value "true"
        if (in_array($key, self::$overrideConfiguration)) {
            return true;
        }

        // Otherwise, it has value "false"
        return false;
    }

    /**
     * Access layer to the environment variable, enabling to override its value
     * Indicate if the configuration can be set through params
     *
     * @return bool
     */
    public static function enableConfigByParams(): bool
    {
        // Define properties
        $envVariable = Environment::ENABLE_CONFIG_BY_PARAMS;
        $selfProperty = &self::$enableConfigByParams;
        $callback = [Environment::class, 'enableConfigByParams'];

        // Initialize property from the environment/hook
        self::maybeInitEnvironmentVariable(
            $envVariable,
            $selfProperty,
            $callback
        );
        return $selfProperty;
    }

    /**
     * Access layer to the environment variable, enabling to override its value
     * Indicate if to use the cache
     *
     * @return bool
     */
    public static function useComponentModelCache(): bool
    {
        // If we are overriding the configuration, then do NOT use the cache
        // Otherwise, parameters from the config have need to be added to $vars, however they can't,
        // since we want the $vars model_instance_id to not change when testing with the "config" param
        if (self::doingOverrideConfiguration()) {
            return false;
        }

        // Define properties
        $envVariable = Environment::USE_COMPONENT_MODEL_CACHE;
        $selfProperty = &self::$useComponentModelCache;
        $callback = [Environment::class, 'useComponentModelCache'];

        // Initialize property from the environment/hook
        self::maybeInitEnvironmentVariable(
            $envVariable,
            $selfProperty,
            $callback
        );
        return $selfProperty;
    }

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

    public static function namespaceTypesAndInterfaces(): bool
    {
        // Define properties
        $envVariable = Environment::NAMESPACE_TYPES_AND_INTERFACES;
        $selfProperty = &self::$namespaceTypesAndInterfaces;
        $callback = [Environment::class, 'namespaceTypesAndInterfaces'];

        // Initialize property from the environment/hook
        self::maybeInitEnvironmentVariable(
            $envVariable,
            $selfProperty,
            $callback
        );
        return $selfProperty;
    }
}
