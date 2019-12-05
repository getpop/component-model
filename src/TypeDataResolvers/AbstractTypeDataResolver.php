<?php
namespace PoP\ComponentModel\TypeDataResolvers;

abstract class AbstractTypeDataResolver implements TypeDataResolverInterface
{
    public function resolveObjectsFromIDs(array $ids): array
    {
        return array();
    }

    public function getDataquery()
    {
        return null;
    }
}
