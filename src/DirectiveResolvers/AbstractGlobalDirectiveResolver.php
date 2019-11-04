<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;

abstract class AbstractGlobalDirectiveResolver extends AbstractSchemaDirectiveResolver
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
