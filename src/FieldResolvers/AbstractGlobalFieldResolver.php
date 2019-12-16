<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\TypeResolvers\AbstractTypeResolver;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

abstract class AbstractGlobalFieldResolver extends AbstractDBDataFieldResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
        	AbstractTypeResolver::class,
        ];
    }

    public function isGlobal(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return true;
    }
}
