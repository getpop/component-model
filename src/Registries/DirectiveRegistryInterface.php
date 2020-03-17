<?php
namespace PoP\ComponentModel\Registries;

interface DirectiveRegistryInterface
{
    public function addDirectiveResolverClass(string $directiveResolverClass): void;
    public function getDirectiveResolverClasses(): array;
}
