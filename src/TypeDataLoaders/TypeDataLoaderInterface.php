<?php
namespace PoP\ComponentModel\TypeDataLoaders;

interface TypeDataLoaderInterface
{
	public function resolveObjectsFromIDs(array $ids): array;
	public function getDataquery();
}
