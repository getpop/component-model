<?php
namespace PoP\ComponentModel\DirectiveResolvers;
use PoP\ComponentModel\GeneralUtils;
use PoP\ComponentModel\DataloaderInterface;
use PoP\Translation\Facades\TranslationAPIFacade;
use PoP\ComponentModel\FieldResolvers\PipelinePositions;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\Facades\Schema\FeedbackMessageStoreFacade;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

class ResolveValueAndMergeDirectiveResolver extends AbstractGlobalDirectiveResolver
{
    const DIRECTIVE_NAME = 'resolveValueAndMerge';
    public static function getDirectiveName(): string {
        return self::DIRECTIVE_NAME;
    }

    /**
     * This directive must be the first one of the group at the back
     *
     * @return void
     */
    public function getPipelinePosition(): string
    {
        return PipelinePositions::BACK;
    }

    public function resolveDirective(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        // Iterate data, extract into final results
        if ($resultIDItems) {
            $this->resolveValueForResultItems($dataloader, $fieldResolver, $resultIDItems, $idsDataFields, $dbItems, $dbErrors, $dbWarnings, $schemaErrors, $schemaWarnings, $schemaDeprecations, $previousDBItems, $variables, $messages);
        }
    }

    protected function resolveValueForResultItems(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, array &$resultIDItems, array &$idsDataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$schemaErrors, array &$schemaWarnings, array &$schemaDeprecations, array &$previousDBItems, array &$variables, array &$messages)
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        foreach (array_keys($idsDataFields) as $id) {
            // Obtain its ID and the required data-fields for that ID
            $resultItem = $resultIDItems[$id];
            // It could be that the object is NULL. For instance: a post has a location stored a meta value, and the corresponding location object was deleted, so the ID is pointing to a non-existing object
            // In that case, simply return a dbError
            if (is_null($resultItem)) {
                $dbErrors[(string)$id]['id'][] = sprintf(
                    $translationAPI->__('Corrupted data: Object with ID \'%s\' doesn\'t exist', 'component-model'),
                    $id
                );
                continue;
            }

            $resultItemVariables = $this->getVariablesForResultItem($id, $variables, $messages);
            $this->resolveValuesForResultItem($dataloader, $fieldResolver, $id, $resultItem, $idsDataFields[(string)$id]['direct'], $dbItems, $dbErrors, $dbWarnings, $previousDBItems, $resultItemVariables);

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
                    $conditionFieldValue = $this->resolveFieldValue($dataloader, $fieldResolver, $id, $resultItem, $conditionDataField, $dbWarnings, $previousDBItems, $resultItemVariables);
                    $conditionSatisfied = $conditionFieldValue && !GeneralUtils::isError($conditionFieldValue);
                }
                if ($conditionSatisfied) {
                    // $conditionalResultIDItems = [$id => $resultItem];
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

    protected function resolveValuesForResultItem(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, $id, $resultItem, array $dataFields, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$previousDBItems, array &$resultItemVariables)
    {
        foreach ($dataFields as $field) {
            $this->resolveValueForResultItem($dataloader, $fieldResolver, $id, $resultItem, $field, $dbItems, $dbErrors, $dbWarnings, $previousDBItems, $resultItemVariables);
        }
    }

    protected function resolveValueForResultItem(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, $id, $resultItem, string $field, array &$dbItems, array &$dbErrors, array &$dbWarnings, array &$previousDBItems, array &$resultItemVariables)
    {
        // Get the value, and add it to the database
        $value = $this->resolveFieldValue($dataloader, $fieldResolver, $id, $resultItem, $field, $dbWarnings, $previousDBItems, $resultItemVariables);
        $this->addValueForResultItem($fieldResolver, $id, $field, $value, $dbItems, $dbErrors);
    }

    protected function resolveFieldValue(DataloaderInterface $dataloader, FieldResolverInterface $fieldResolver, $id, $resultItem, string $field, array &$dbWarnings, array &$previousDBItems, array &$resultItemVariables)
    {
        $value = $fieldResolver->resolveValue($resultItem, $field, $resultItemVariables);
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

    public function getSchemaDirectiveDescription(FieldResolverInterface $fieldResolver): ?string
    {
        $translationAPI = TranslationAPIFacade::getInstance();
        return $translationAPI->__('Resolve the value of the field and merge it into results. This directive is already included by the engine, since its execution is mandatory', 'component-model');
    }
}
