<?php
namespace PoP\ComponentModel\Schema;

interface FeedbackMessageStoreInterface extends \PoP\FieldQuery\FeedbackMessageStoreInterface
{
    function addDBWarnings(array $dbWarnings);
    function addSchemaWarnings(array $schemaWarnings);
    function retrieveAndClearResultItemDBWarnings($resultItemID): ?array;
    function maybeAddSchemaError(string $dbKey, string $field, string $error);
    function getSchemaErrors(): array;
    function getSchemaWarnings(): array;
    function getSchemaErrorsForField(string $dbKey, string $field): ?array;
    function maybeAddLogEntry(string $entry): void;
    function getLogEntries(): array;
}
