<?php
namespace PoP\ComponentModel\ModuleProcessors;

use PoP\ComponentModel\Schema\QueryHelpers;
use PoP\ComponentModel\Facades\Schema\FieldQueryInterpreterFacade;

abstract class AbstractRelationalFieldQueryDataModuleProcessor extends AbstractQueryDataModuleProcessor
{
    protected function getFields(array $module, $moduleAtts): array
    {
        // If it is a virtual module, the fields are coded inside the virtual module atts
        if (!is_null($moduleAtts)) {
            return $moduleAtts['fields'];
        }
        // If it is a normal module, it is the first added, then simply get the fields from $vars
        $vars = \PoP\ComponentModel\Engine_Vars::getVars();
        return $vars['fields'] ?? [];
    }

    /**
     * Property fields: Those fields which have a numeric key only
     *
     * @param array $module
     * @return array
     */
    protected function getPropertyFields(array $module): array
    {
        $moduleAtts = count($module) >= 3 ? $module[2] : null;
        $fields = $this->getFields($module, $moduleAtts);

        $fields = array_values(array_filter(
            $fields,
            function ($key) {
                return is_numeric($key);
            },
            ARRAY_FILTER_USE_KEY
        ));

        // Only allow from a specific list of fields. Precaution against hackers.
        $dataquery_manager = \PoP\ComponentModel\DataQueryManagerFactory::getInstance();
        return $dataquery_manager->filterAllowedfields($fields);
    }

    /**
     * Nested fields: Those fields which have a field as key and an array of submodules as value
     *
     * @param array $module
     * @return array
     */
    protected function getFieldsWithNestedSubfields(array $module): array
    {
        $moduleAtts = count($module) >= 3 ? $module[2] : null;
        $fields = $this->getFields($module, $moduleAtts);

        $fieldNestedFields = array_filter(
            $fields,
            function ($key) {
                return !is_numeric($key);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Only allow from a specific list of fields. Precaution against hackers.
        $dataquery_manager = \PoP\ComponentModel\DataQueryManagerFactory::getInstance();
        $allowedFields = $dataquery_manager->filterAllowedfields(array_keys($fieldNestedFields));
        return array_filter(
            $fieldNestedFields,
            function ($field) use ($allowedFields) {
                return in_array($field, $allowedFields);
            },
            ARRAY_FILTER_USE_KEY
        );
    }

    /**
     * Replace the "skip output if null" fields with their not(isNull($field)) corresponding version
     *
     * @param array $fields
     * @return array
     */
    protected function replaceSkipOutputIfNullFields(array $fields): array
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        return array_map(
            function ($field) use ($fieldQueryInterpreter) {
                // If the field has a "?", then it must not be output if its value is null
                // To achieve this, we replace this field with a conditionField "not(isNull($field))", and then $field is conditional to it
                if ($fieldQueryInterpreter->isSkipOuputIfNullField($field)) {
                    return $this->getNotIsEmptyConditionField($field);
                }
                return $field;
            },
            $fields
        );
    }

    /**
     * Given a field, return its corresponding "not(isEmpty($field))
     *
     * @param array $fields
     * @return array
     */
    protected function getNotIsEmptyConditionField(string $field): string
    {
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();
        // Convert the field into its "not is null" version
        if ($fieldAlias = $fieldQueryInterpreter->getFieldAlias($field)) {
            $conditionFieldAlias = 'not-isnull-'.$fieldAlias;
        }
        return $fieldQueryInterpreter->getField(
            'not',
            [
                'value' => $fieldQueryInterpreter->getField(
                    'isNull',
                    [
                        'value' => $fieldQueryInterpreter->composeField(
                            $fieldQueryInterpreter->getFieldName($field),
                            $fieldQueryInterpreter->getFieldArgs($field) ?? QueryHelpers::getEmptyFieldArgs()
                        ),
                    ]
                ),
            ],
            $conditionFieldAlias,
            false,
            $fieldQueryInterpreter->getDirectives($field)
        );
    }

    public function getDataFields(array $module, array &$props): array
    {
        // The fields which have a numeric key only are the data-fields for the current module level
        $fields = $this->getPropertyFields($module);

        // Replace the "skip output if null" fields with their not(isNull($field)) corresponding version
        $fields = $this->replaceSkipOutputIfNullFields($fields);

        return $fields;
    }

    public function getDomainSwitchingSubmodules(array $module): array
    {
        $ret = parent::getDomainSwitchingSubmodules($module);
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        // The fields which are not numeric are the keys from which to switch database domain
        $fieldNestedFields = $this->getFieldsWithNestedSubfields($module);

        // Process only the fields without "skip output if null". Those will be processed on function `getConditionalOnDataFieldDomainSwitchingSubmodules`
        $fieldNestedFields = array_filter(
            $this->getFieldsWithNestedSubfields($module),
            function ($field) use ($fieldQueryInterpreter) {
                return !$fieldQueryInterpreter->isSkipOuputIfNullField($field);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Create a "virtual" module with the fields corresponding to the next level module
        foreach ($fieldNestedFields as $field => $nestedFields) {
            $ret[$field] = array(
                POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD => array(
                    [
                        $module[0],
                        $module[1],
                        ['fields' => $nestedFields]
                    ],
                ),
            );
        }
        return $ret;
    }

    public function getConditionalOnDataFieldSubmodules(array $module): array
    {
        $ret = parent::getConditionalOnDataFieldSubmodules($module);
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        // Calculate the property fields with "skip output if null" on true
        $propertyFields = array_filter(
            $this->getPropertyFields($module),
            function ($field) use ($fieldQueryInterpreter) {
                return $fieldQueryInterpreter->isSkipOuputIfNullField($field);
            }
        );
        $relationalFields = array_keys(array_filter(
            $this->getFieldsWithNestedSubfields($module),
            function ($field) use ($fieldQueryInterpreter) {
                return $fieldQueryInterpreter->isSkipOuputIfNullField($field);
            },
            ARRAY_FILTER_USE_KEY
        ));
        $fields = array_values(array_unique(array_merge(
            $propertyFields,
            $relationalFields
        )));

        // Create a "virtual" module with the fields corresponding to the next level module
        foreach ($fields as $field) {
            $conditionField = $this->getNotIsEmptyConditionField($field);
            $conditionalField = $fieldQueryInterpreter->removeSkipOuputIfNullFromField($field);
            $ret[$conditionField][] = [
                $module[0],
                $module[1],
                ['fields' => [$conditionalField]]
            ];
        }

        return $ret;
    }

    public function getConditionalOnDataFieldDomainSwitchingSubmodules(array $module): array
    {
        $ret = parent::getConditionalOnDataFieldDomainSwitchingSubmodules($module);
        $fieldQueryInterpreter = FieldQueryInterpreterFacade::getInstance();

        // Calculate the nested fields with "skip output if null" on true
        $fieldNestedFields = array_filter(
            $this->getFieldsWithNestedSubfields($module),
            function ($field) use ($fieldQueryInterpreter) {
                return $fieldQueryInterpreter->isSkipOuputIfNullField($field);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($fieldNestedFields as $field => $nestedFields) {
            $conditionField = $this->getNotIsEmptyConditionField($field);
            $conditionalField = $fieldQueryInterpreter->removeSkipOuputIfNullFromField($field);
            $ret[$conditionField][$conditionalField] = array(
                POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD => array(
                    [
                        $module[0],
                        $module[1],
                        ['fields' => $nestedFields]
                    ],
                ),
            );
        }

        return $ret;
    }
}
