<?php
namespace PoP\ComponentModel\DirectiveResolvers;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\GeneralUtils;

class ResolveValueAndMergeDirectiveResolver extends AbstractDirectiveResolver
{
    const DIRECTIVE_NAME = 'resolve-value-and-merge';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    public function resolveDirective(FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        // Iterate data, extract into final results
        if ($resultIDItems) {
            $this->resolveValueForResultItems($fieldResolver, $resultIDItems, $idsDataFields, $dbItems, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations);
        }
    }

    protected function resolveValueForResultItems(FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations)
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        foreach (array_keys($idsDataFields) as $id) {
            // Obtain its ID and the required data-fields for that ID
            $resultItem = $resultIDItems[$id];
            // $conditionalResultIDItems = [$id => $resultItem];
            $this->resolveValuesForResultItem($fieldResolver, $id, $resultItem, $idsDataFields[(string)$id]['direct'], $dbItems, $dbErrors, $dbWarnings);

            // Add the conditional data fields
            // If the conditionalDataFields are empty, we already reached the end of the tree. Nothing else to do
            foreach (array_filter($idsDataFields[$id]['conditional']) as $conditionDataField => $conditionalDataFields) {
                // Check if the condition field has value `true`
                // There are 2 possibilities:
                // 1. `outputConditionFields` => true in the ModuleProcessor: The conditionField has been defined as to be resolved, then it will be in $dbItems and can be retrieved from there
                // 2. `outputConditionFields` => false in the ModuleProcessor: The conditionField is not present in $dbItems, so it must be resolved now
                $conditionFieldOutputKey = $fieldQueryInterpreter->getFieldOutputKey($conditionDataField);
                if (isset($dbItems[$id]) && array_key_exists($conditionFieldOutputKey, $dbItems[$id])) {
                    $conditionSatisfied = (bool)$dbItems[$id][$conditionFieldOutputKey];
                } else {
                    $conditionFieldValue = $this->resolveFieldValue($fieldResolver, $id, $resultItem, $conditionDataField, $dbWarnings);
                    $conditionSatisfied = $conditionFieldValue && !GeneralUtils::isError($conditionFieldValue);
                }
                if ($conditionSatisfied) {
                    $fieldResolver->enqueueFillingResultItemsFromIDs(
                        [
                            (string)$id => [
                                'direct' => array_keys($conditionalDataFields),
                                'conditional' => $conditionalDataFields,
                            ],
                        ],
                        $resultIDItems
                    );
                }
            }
        }
    }

    protected function resolveValuesForResultItem(FieldResolverInterface $fieldResolver, $id, $resultItem, array $dataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings)
    {
        foreach ($dataFields as $field) {
            $this->resolveValueForResultItem($fieldResolver, $id, $resultItem, $field, $dbItems, $dbErrors, $dbWarnings);
        }
    }

    protected function resolveValueForResultItem(FieldResolverInterface $fieldResolver, $id, $resultItem, string $field, array &$dbItems, array &$dbErrors, array &$dbWarnings)
    {
        // Get the value, and add it to the database
        $value = $this->resolveFieldValue($fieldResolver, $id, $resultItem, $field, $dbWarnings);
        $this->addValueForResultItem($fieldResolver, $id, $field, $value, $dbItems, $dbErrors);
    }

    protected function resolveFieldValue(FieldResolverInterface $fieldResolver, $id, $resultItem, string $field, array &$dbWarnings)
    {
        $value = $fieldResolver->resolveValue($resultItem, $field);
        // Merge the dbWarnings, if any
        $feedbackMessageStore = FeedbackMessageStoreFacade::getInstance();
        if ($resultItemDBWarnings = $feedbackMessageStore->retrieveAndClearResultItemDBWarnings($id)) {
            $dbWarnings[$id] = array_merge(
                $dbWarnings[$id] ?? [],
                $resultItemDBWarnings
            );
        }

        return $value;
    }

    protected function addValueForResultItem(FieldResolverInterface $fieldResolver, $id, string $field, $value, array &$dbItems, array &$dbErrors)
    {
        // If there is an alias, store the results under this. Otherwise, on the fieldName+fieldArgs
        $fieldOutputKey = FieldQueryInterpreterFacade::getInstance()->getFieldOutputKey($field);

        // The dataitem can contain both rightful values and also errors (eg: when the field doesn't exist, or the field validation fails)
        // Extract the errors and add them on the other array
        if (GeneralUtils::isError($value)) {
            // Extract the error message
            $error = $value;
            $dbErrors[(string)$id][$fieldOutputKey] = array_merge(
                $dbErrors[(string)$id][$fieldOutputKey] ?? [],
                $error->getErrorMessages()
            );
        } else {
            $dbItems[(string)$id][$fieldOutputKey] = $value;
        }
    }
}
