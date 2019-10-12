<?php
namespace PoP\ComponentModel\ModuleProcessors;

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

    public function getDataFields(array $module, array &$props): array
    {
        $moduleAtts = count($module) >= 3 ? $module[2] : null;
        $fields = $this->getFields($module, $moduleAtts);

        // Keep the fields which have a numeric key only: those are the data-fields for the current module level
        $fields = array_filter(
            $fields,
            function ($key) {
                return is_numeric($key);
            },
            ARRAY_FILTER_USE_KEY
        );

        // Only allow from a specific list of fields. Precaution against hackers.
        $dataquery_manager = \PoP\ComponentModel\DataQueryManagerFactory::getInstance();
        return $dataquery_manager->filterAllowedfields($fields);
    }

    public function getDomainSwitchingSubmodules(array $module): array
    {
        $ret = parent::getDomainSwitchingSubmodules($module);

        $moduleAtts = count($module) >= 3 ? $module[2] : null;
        $fields = $this->getFields($module, $moduleAtts);

        // Keep the fields which are not numeric: these are the keys from which to switch database domain
        $fields = array_filter(
            $fields,
            function ($key) {
                return !is_numeric($key);
            },
            ARRAY_FILTER_USE_KEY
        );

        foreach ($fields as $field => $subfields) {
            // Create a "virtual" module with the fields corresponding to the next level module
            $ret[$field] = array(
                POP_CONSTANT_SUBCOMPONENTDATALOADER_DEFAULTFROMFIELD => array(
                    [
                        $module[0],
                        $module[1],
                        ['fields' => $subfields]
                    ],
                ),
            );
        }

        return $ret;
    }
}
