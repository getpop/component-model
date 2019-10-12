<?php
namespace PoP\ComponentModel\ModuleProcessors;

abstract class AbstractRelationalFieldDataloadModuleProcessor extends AbstractDataloadModuleProcessor
{
    protected function getInnerSubmodules(array $module): array
    {
        $ret = parent::getInnerSubmodules($module);
        // The fields to retrieve are passed through module atts, so simply transfer all module atts down the line
        $ret[] = [PoP_Module_Processor_DataQuery_RelationalFields::class, PoP_Module_Processor_DataQuery_RelationalFields::MODULE_LAYOUT_DATAQUERY_RELATIONALFIELDS, $module[2]];
        return $ret;
    }

    public function getFormat(array $module): ?string
    {
        return POP_FORMAT_FIELDS;
    }
}
