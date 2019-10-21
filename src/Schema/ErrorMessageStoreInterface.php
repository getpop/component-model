<?php
namespace PoP\ComponentModel\Schema;

interface ErrorMessageStoreInterface extends \PoP\FieldQuery\Query\ErrorMessageStoreInterface
{
    function addDBWarnings(array $dbWarnings);
    function retrieveAndClearResultItemDBWarnings($resultItemID): ?array;
    function maybeAddSchemaError(string $dbKey, string $field, string $error);
    function getSchemaErrors(): array;
    function getSchemaErrorsForField(string $dbKey, string $field): ?array;
    function addLogEntry(string $entry): void;
    function getLogEntries(): array;
}
