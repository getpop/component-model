<?php
namespace PoP\ComponentModel\Schema;

class ErrorMessageStore implements ErrorMessageStoreInterface
{
    protected $schemaErrors = array();
    protected $queryErrors = array();

    public function maybeAddSchemaError(string $dbKey, string $field, string $error)
    {
        // Avoid adding several times the same error (which happens when calling `getDefaultDataloaderNameFromSubcomponentDataField` from different functions)
        if (!in_array($error, self::$schemaErrors[$dbKey][$field] ?? [])) {
            self::$schemaErrors[$dbKey][$field][] = $error;
        }
    }
    public function getSchemaErrors(): array
    {
        return self::$schemaErrors;
    }
    public function getSchemaErrorsForField(string $dbKey, string $field): ?array
    {
        return self::$schemaErrors[$dbKey][$field];
    }
    public function addQueryError(string $error)
    {
        self::$queryErrors[] = $error;
    }
    public function getQueryErrors(): array
    {
        return array_unique(self::$queryErrors);
    }
}
