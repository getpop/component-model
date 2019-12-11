<?php
namespace PoP\ComponentModel\TypeDataLoaders;

interface TypeQueryableDataResolverInterface
{
    public function findIDs(array $data_properties): array;
    public function getFilterDataloadingModule(): ?array;
}
