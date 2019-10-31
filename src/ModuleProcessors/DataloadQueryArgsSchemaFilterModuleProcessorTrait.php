<?php
namespace PoP\ComponentModel\ModuleProcessors;

trait DataloadQueryArgsSchemaFilterModuleProcessorTrait
{
    use DataloadQueryArgsFilterModuleProcessorTrait;

    public function getFilterInputSchemaDefinitionResolver(array $module): ?DataloadQueryArgsSchemaFilterModuleProcessorInterface
    {
        return $this;
    }

    public function getFilterDocumentationType(array $module): ?string
    {
        return null;
    }
    public function getFilterDocumentationDescription(array $module): ?string
    {
        return null;
    }
    public function getFilterDocumentationDeprecationDescription(array $module): ?string
    {
        return null;
    }
    public function getFilterDocumentationMandatory(array $module): bool
    {
        return false;
    }
}
