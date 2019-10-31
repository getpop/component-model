<?php
namespace PoP\ComponentModel\ModuleProcessors;

trait DataloadQueryArgsSchemaFilterInputModuleProcessorTrait
{
    use DataloadQueryArgsFilterInputModuleProcessorTrait;

    public function getFilterInputSchemaDefinitionResolver(array $module): ?DataloadQueryArgsSchemaFilterInputModuleProcessorInterface
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
