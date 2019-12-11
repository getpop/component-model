<?php
namespace PoP\ComponentModel\TypeDataLoaders;

interface TypeQueryableDataResolverInterface
{
    public function resolveIDsFromDataProperties(array $data_properties);
    public function getFilterDataloadingModule(): ?array;
}
