<?php

declare(strict_types=1);

namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Resolvers\QueryableFieldResolverTrait;
use PoP\ComponentModel\Facades\Instances\InstanceManagerFacade;
use PoP\ComponentModel\TypeDataLoaders\TypeQueryableDataLoaderInterface;

abstract class AbstractQueryableFieldResolver extends AbstractDBDataFieldResolver
{
    use QueryableFieldResolverTrait;

    protected function getFieldArgumentsSchemaDefinitions(TypeResolverInterface $typeResolver, string $fieldName, array $fieldArgs = []): array
    {
        $schemaDefinitions = parent::getFieldArgumentsSchemaDefinitions($typeResolver, $fieldName, $fieldArgs);

        if ($filterDataloadingModule = $this->getFieldDefaultFilterDataloadingModule($typeResolver, $fieldName, $fieldArgs)) {
            $schemaDefinitions = array_merge(
                $schemaDefinitions,
                $this->getFilterSchemaDefinitionItems($filterDataloadingModule)
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
