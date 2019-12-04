<?php
namespace PoP\ComponentModel\FieldValueResolvers;

use PoP\ComponentModel\TypeResolvers\AbstractTypeResolver;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;

abstract class AbstractOperatorOrHelperFieldValueResolver extends AbstractDBDataFieldValueResolver
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
