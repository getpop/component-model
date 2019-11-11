<?php
namespace PoP\ComponentModel\FieldValueResolvers;

use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;
use PoP\ComponentModel\FieldResolvers\FieldResolverInterface;

abstract class AbstractOperatorOrHelperFieldValueResolver extends AbstractDBDataFieldValueResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
        	AbstractFieldResolver::class,
        ];
    }

    public function isOperatorOrHelper(FieldResolverInterface $fieldResolver, string $fieldName): bool
    {
        return true;
    }
}
