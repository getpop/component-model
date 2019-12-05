<?php
namespace PoP\ComponentModel\TypeDataResolvers;

interface TypeQueryableDataResolverInterface
{
    public function resolveIDsFromDataProperties(array $data_properties);
    public function getFilterDataloadingModule(): ?array;
}
