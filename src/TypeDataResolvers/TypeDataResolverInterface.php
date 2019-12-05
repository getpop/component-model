<?php
namespace PoP\ComponentModel\TypeDataResolvers;

interface TypeDataResolverInterface
{
    public function resolveObjectsFromIDs(array $ids): array;
	public function getTypeResolverClass(): string;
    public function getDatabaseKey();
    public function getDataquery();
}
