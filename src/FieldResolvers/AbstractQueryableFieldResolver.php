<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\Misc\GeneralUtils;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\TypeDataLoaders\TypeQueryableDataLoaderInterface;
use PoP\ComponentModel\Facades\ModuleProcessors\ModuleProcessorManagerFacade;

abstract class AbstractQueryableFieldResolver extends AbstractDBDataFieldResolver
{
    protected function getFieldArgumentsSchemaDefinitions(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): array
    {
        $schemaDefinitions = parent::getFieldArgumentsSchemaDefinitions($typeResolver, $fieldName, $fieldArgs);

        if ($filterDataloadingModule = $this->getFieldDefaultFilterDataloadingModule($typeResolver, $fieldName, $fieldArgs)) {
            $moduleprocessor_manager = ModuleProcessorManagerFacade::getInstance();
            $filterqueryargs_modules = $moduleprocessor_manager->getProcessor((array)$filterDataloadingModule)->getDataloadQueryArgsFilteringModules($filterDataloadingModule);
            $schemaDefinitions = array_merge(
                $schemaDefinitions,
                GeneralUtils::arrayFlatten(array_map(function($module) use($moduleprocessor_manager) {
                    return $moduleprocessor_manager->getProcessor($module)->getFilterInputSchemaDefinitionItems($module);
                }, $filterqueryargs_modules))
            );
        }

        return $schemaDefinitions;
    }

    protected function getFieldDefaultFilterDataloadingModule(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): ?array
    {
        $instanceManager = InstanceManagerFacade::getInstance();
        if ($fieldTypeResolverClass = $this->resolveFieldTypeResolverClass($typeResolver, $fieldName, $fieldArgs)) {
            $fieldTypeResolver = $instanceManager->getInstance((string)$fieldTypeResolverClass);
            $fieldTypeDataLoaderClass = $fieldTypeResolver->getTypeDataLoaderClass();
            $fieldTypeDataLoader = $instanceManager->getInstance((string)$fieldTypeDataLoaderClass);
            if ($fieldTypeDataLoader instanceof TypeQueryableDataLoaderInterface) {
                return $fieldTypeDataLoader->getFilterDataloadingModule();
            }
        }
        return null;
    }

    protected function addFilterDataloadQueryArgs(array &$options, TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = [])
    {
        $options['filter-dataload-query-args'] = [
            'source' => $fieldArgs,
            'module' => $this->getFieldDefaultFilterDataloadingModule($typeResolver, $fieldName, $fieldArgs),
        ];
    }
}
