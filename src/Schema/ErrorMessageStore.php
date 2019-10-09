<?php
namespace PoP\ComponentModel\Schema;

class ErrorMessageStore implements ErrorMessageStoreInterface
{
    protected $schemaErrors = [];
    protected $queryErrors = [];
    protected $dbWarnings = [];

    public function addDBWarnings(array $dbWarnings)
    {
        foreach ($dbWarnings as $resultItemID => $resultItemWarnings) {
            $this->dbWarnings[$resultItemID] = array_merge(
                $this->dbWarnings[$resultItemID] ?? [],
                $resultItemWarnings
            );
        }
    }
    // public function clearDBWarnings()
    // {
    //     $this->dbWarnings = [];
    // }
    public function retrieveAndClearResultItemDBWarnings($resultItemID): ?array
    {
        $resultItemDBWarnings = $this->dbWarnings[$resultItemID];
        unset($this->dbWarnings[$resultItemID]);
        return $resultItemDBWarnings;
    }

    public function maybeAddSchemaError(string $dbKey, string $field, string $error)
    {
        // Avoid adding several times the same error (which happens when calling `getDefaultDataloaderNameFromSubcomponentDataField` from different functions)
        if (!in_array($error, $this->schemaErrors[$dbKey][$field] ?? [])) {
            $this->schemaErrors[$dbKey][$field][] = $error;
        }
    }
    public function getSchemaErrors(): array
    {
        return $this->schemaErrors;
    }
    public function getSchemaErrorsForField(string $dbKey, string $field): ?array
    {
        return $this->schemaErrors[$dbKey][$field];
    }
    public function addQueryError(string $error)
    {
        $this->queryErrors[] = $error;
    }
    public function getQueryErrors(): array
    {
        return array_unique($this->queryErrors);
    }
}
