<?php
namespace PoP\ComponentModel\TypeDataLoaders;

interface TypeDataLoaderInterface
{
	public function getObjects(array $ids): array;
	public function getDataquery();
}
