<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

trait GlobalDirectiveResolverTrait
{
    public static function getClassesToAttachTo(): array
    {
        // Be attached to all fieldResolvers
        return [
            AbstractFieldResolver::class,
        ];
    }

    public function isGlobal(FieldResolverInterface $fieldResolver): bool
    {
        return true;
    }
}
