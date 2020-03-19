<?php
namespace PoP\ComponentModel;

use PoP\Hooks\Facades\HooksAPIFacade;

abstract class AbstractComponentConfiguration
{
    protected static $initialized = [];
    public static function getHookName(string $class, string $envVariable): string
    {
        return sprintf(
            '%s:configuration:%s',
            $class,
            $envVariable
        );
    }
    protected static function maybeInitEnvironmentVariable(string $envVariable, &$selfProperty, callable $callback): void
    {
        if (!self::$initialized[$envVariable]) {
            self::$initialized[$envVariable] = true;
            $hooksAPI = HooksAPIFacade::getInstance();
            $class = get_called_class();
            $hookName = self::getHookName(
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

