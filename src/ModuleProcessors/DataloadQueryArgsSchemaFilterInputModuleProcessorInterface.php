<?php
namespace PoP\ComponentModel\ModuleProcessors;

interface DataloadQueryArgsSchemaFilterInputModuleProcessorInterface
{
    public function getFilterDocumentationType(array $module): ?string;
    public function getFilterDocumentationDescription(array $module): ?string;
    public function getFilterDocumentationDeprecationDescription(array $module): ?string;
    public function getFilterDocumentationMandatory(array $module): bool;
}
