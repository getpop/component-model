<?php
namespace PoP\ComponentModel\TypeDataResolvers;
use PoP\ComponentModel\TypeDataResolvers\AbstractTypeQueryableDataResolver;

class NilTypeQueryableDataResolver extends AbstractTypeQueryableDataResolver
{
    public function resolveObjectsFromIDs(array $ids): array
    {
        return [];
    }

    public function resolveIDsFromDataProperties(array $data_properties)
    {
        return [];
    }

    public function getTypeResolverClass(): string
    {
    	return null;
    }

    public function getDatabaseKey()
    {
        return null;
    }
}

