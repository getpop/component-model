<?php
namespace PoP\ComponentModel\ModuleProcessors;

trait DataloadQueryArgsSchemaFilterInputModuleProcessorTrait
{
    use DataloadQueryArgsFilterInputModuleProcessorTrait;

    public function getFilterInputSchemaDefinitionResolver(array $module): ?DataloadQueryArgsSchemaFilterInputModuleProcessorInterface
    {
        return $this;
    }

    public function getSchemaFilterInputType(array $module): ?string
    {
        return null;
    }
    public function getSchemaFilterInputDescription(array $module): ?string
    {
        return null;
    }
    public function getSchemaFilterInputDeprecationDescription(array $module): ?string
    {
        return null;
    }
    public function getSchemaFilterInputMandatory(array $module): bool
    {
        return false;
    }
}
