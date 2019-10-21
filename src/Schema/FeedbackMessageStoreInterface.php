<?php
namespace PoP\ComponentModel\Schema;

interface FeedbackMessageStoreInterface extends \PoP\FieldQuery\Query\FeedbackMessageStoreInterface
{
    function addDBWarnings(array $dbWarnings);
    function retrieveAndClearResultItemDBWarnings($resultItemID): ?array;
    function maybeAddSchemaError(string $dbKey, string $field, string $error);
    function getSchemaErrors(): array;
    function getSchemaErrorsForField(string $dbKey, string $field): ?array;
    function addLogEntry(string $entry): void;
    function getLogEntries(): array;
}
