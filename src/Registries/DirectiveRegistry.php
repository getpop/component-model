<?php
namespace PoP\ComponentModel\Registries;

class DirectiveRegistry implements DirectiveRegistryInterface
{
    protected $directiveResolverClasses = [];
    public function addDirectiveResolverClass(string $directiveResolverClass): void
    {
        $this->directiveResolverClasses[] = $directiveResolverClass;
    }
    public function getDirectiveResolverClasses(): array
    {
        return $this->directiveResolverClasses;
    }
}
