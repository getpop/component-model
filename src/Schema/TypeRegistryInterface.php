<?php
namespace PoP\ComponentModel\Schema;

interface TypeRegistryInterface
{
    public function addTypeResolverClass(string $typeResolverClass): void;
    public function getTypeResolverClasses(): array;
}
