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
            $hookName = self::getHookName(
                get_called_class(),
                $envVariable
            );
            $selfProperty = $hooksAPI->applyFilters(
                $hookName,
                $callback()
            );
        }
    }
}

