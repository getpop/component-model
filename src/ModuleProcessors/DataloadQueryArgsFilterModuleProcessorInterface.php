<?php
namespace PoP\ComponentModel\ModuleProcessors;

interface DataloadQueryArgsFilterModuleProcessorInterface
{
    public function getValue(array $module, ?array $source = null);
    public function getFilterInput(array $module): ?array;
    public function getFilterSchemaDefinitionItems(array $module): array;
    public function getFilterSchemaDefinitionResolver(array $module): ?DataloadQueryArgsSchemaFilterModuleProcessorInterface;
}
