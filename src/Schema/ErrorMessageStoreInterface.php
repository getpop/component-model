<?php
namespace PoP\ComponentModel\Schema;

interface ErrorMessageStoreInterface
{
    function addDBWarnings(array $dbWarnings);
    // function clearDBWarnings();
    function retrieveAndClearResultItemDBWarnings($resultItemID): array;
    function maybeAddSchemaError(string $dbKey, string $field, string $error);
    function getSchemaErrors(): array;
    function getSchemaErrorsForField(string $dbKey, string $field): ?array;
    function addQueryError(string $error);
    function getQueryErrors(): array;
}
