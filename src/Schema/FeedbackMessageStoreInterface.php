<?php
namespace PoP\ComponentModel\Schema;

interface FeedbackMessageStoreInterface extends \PoP\FieldQuery\FeedbackMessageStoreInterface
{
    function addDBWarnings(array $dbWarnings);
    function addSchemaWarnings(array $schemaWarnings);
    function retrieveAndClearResultItemDBWarnings($resultItemID): ?array;
    function addSchemaError(string $dbKey, string $field, string $error);
    function retrieveAndClearSchemaErrors(): array;
    function retrieveAndClearSchemaWarnings(): array;
    function getSchemaErrorsForField(string $dbKey, string $field): ?array;
    function maybeAddLogEntry(string $entry): void;
    function getLogEntries(): array;
}
