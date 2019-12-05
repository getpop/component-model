<?php
namespace PoP\ComponentModel\TypeDataResolvers;

abstract class AbstractTypeQueryableDataResolver extends AbstractTypeDataResolver implements TypeQueryableDataResolverInterface
{
    public function resolveIDsFromDataProperties(array $data_properties)
    {
        return array();
    }

    public function getFilterDataloadingModule(): ?array
    {
        return null;
    }
}
