<?php
namespace PoP\ComponentModel\Registries;

class TypeRegistry implements TypeRegistryInterface
{
    protected $typeResolverClasses = [];
    public function addTypeResolverClass(string $typeResolverClass): void
    {
        $this->typeResolverClasses[] = $typeResolverClass;
    }
    public function getTypeResolverClasses(): array
    {
        return $this->typeResolverClasses;
    }
}
