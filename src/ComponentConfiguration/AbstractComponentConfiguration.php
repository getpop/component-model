<?php

declare(strict_types=1);

namespace PoP\ComponentModel\ComponentConfiguration;

use PoP\Hooks\Facades\HooksAPIFacade;

/**
 * Base class providing an access layer to an environment variable, enabling to override its value
 */
abstract class AbstractComponentConfiguration
{
    protected static $initialized = [];
    protected static function maybeInitEnvironmentVariable(string $envVariable, &$selfProperty, callable $callback): void
    {
        if (!self::$initialized[$envVariable]) {
            self::$initialized[$envVariable] = true;
            $hooksAPI = HooksAPIFacade::getInstance();
            $class = get_called_class();
            $hookName = ComponentConfigurationHelpers::getHookName(
                $class,
                $envVariable
            );
            $selfProperty = $hooksAPI->applyFilters(
                $hookName,
                $callback(),
                $class,
                $envVariable
            );
        }
    }
}
