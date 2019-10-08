<?php
namespace PoP\ComponentModel\Schema;

class ErrorMessageStore
{
    protected static $schemaErrors = array();
    protected static $queryErrors = array();

    public static function maybeAddSchemaError(string $dbKey, string $field, string $error)
    {
        // Avoid adding several times the same error (which happens when calling `getDefaultDataloaderNameFromSubcomponentDataField` from different functions)
        if (!in_array($error, self::$schemaErrors[$dbKey][$field] ?? [])) {
            self::$schemaErrors[$dbKey][$field][] = $error;
        }
    }
    public static function getSchemaErrors(): array
    {
        return self::$schemaErrors;
    }
    public static function getSchemaErrorsForField(string $dbKey, string $field): ?array
    {
        return self::$schemaErrors[$dbKey][$field];
    }
    public static function addQueryError(string $error)
    {
        self::$queryErrors[] = $error;
    }
    public static function getQueryErrors(): array
    {
        return array_unique(self::$queryErrors);
    }
}
