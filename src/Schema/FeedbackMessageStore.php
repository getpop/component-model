<?php
namespace PoP\ComponentModel\Schema;

use PoP\ComponentModel\Feedback\Tokens;

class FeedbackMessageStore extends \PoP\FieldQuery\FeedbackMessageStore implements FeedbackMessageStoreInterface
{
    protected $schemaWarnings = [];
    protected $schemaErrors = [];
    protected $dbWarnings = [];
    protected $dbDeprecations = [];
    protected $logEntries = [];

    public function addDBWarnings(array $dbWarnings)
    {
        foreach ($dbWarnings as $resultItemID => $resultItemWarnings) {
            $this->dbWarnings[$resultItemID] = array_merge(
                $this->dbWarnings[$resultItemID] ?? [],
                $resultItemWarnings
            );
        }
    }
    public function addDBDeprecations(array $dbDeprecations)
    {
        foreach ($dbDeprecations as $resultItemID => $resultItemDeprecations) {
            $this->dbDeprecations[$resultItemID] = array_merge(
                $this->dbDeprecations[$resultItemID] ?? [],
                $resultItemDeprecations
            );
        }
    }
    public function addSchemaWarnings(array $schemaWarnings)
    {
        $this->schemaWarnings = array_merge(
            $this->schemaWarnings,
            $schemaWarnings
        );
    }
    public function retrieveAndClearResultItemDBWarnings($resultItemID): ?array
    {
        $resultItemDBWarnings = $this->dbWarnings[$resultItemID];
        unset($this->dbWarnings[$resultItemID]);
        return $resultItemDBWarnings;
    }
    public function retrieveAndClearResultItemDBDeprecations($resultItemID): ?array
    {
        $resultItemDBDeprecations = $this->dbDeprecations[$resultItemID];
        unset($this->dbDeprecations[$resultItemID]);
        return $resultItemDBDeprecations;
    }

    public function addSchemaError(string $dbKey, string $field, string $error)
    {
        $this->schemaErrors[$dbKey][] = [
            Tokens::PATH => [$field],
            Tokens::MESSAGE => $error,
        ];
    }
    public function retrieveAndClearSchemaErrors(): array
    {
        $schemaErrors = $this->schemaErrors ?? [];
        $this->schemaErrors = null;
        return $schemaErrors;
    }
    public function retrieveAndClearSchemaWarnings(): array
    {
        $schemaWarnings = $this->schemaWarnings ?? [];
        $this->schemaWarnings = null;
        return $schemaWarnings;
    }
    public function getSchemaErrorsForField(string $dbKey, string $field): ?array
    {
        return $this->schemaErrors[$dbKey][$field];
    }

    public function addLogEntry(string $entry): void {
        $this->logEntries[] = $entry;
    }

    public function maybeAddLogEntry(string $entry): void {
        if (!in_array($entry, $this->logEntries)) {
            $this->addLogEntry($entry);
        }
    }

    public function getLogEntries(): array {
        return $this->logEntries;
    }
}
