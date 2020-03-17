<?php
namespace PoP\ComponentModel\Registries;

interface TypeRegistryInterface
{
    public function addTypeResolverClass(string $typeResolverClass): void;
    public function getTypeResolverClasses(): array;
}
