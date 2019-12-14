<?php
namespace PoP\ComponentModel\TypeDataLoaders;

interface TypeQueryableDataLoaderInterface
{
    public function findIDs(array $data_properties): array;
    public function getFilterDataloadingModule(): ?array;
}
