<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\TypeResolvers\AbstractTypeResolver;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

abstract class AbstractOperatorOrHelperFieldResolver extends AbstractDBDataFieldResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
        	AbstractTypeResolver::class,
        ];
    }

    public function isOperatorOrHelper(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return true;
    }
}
