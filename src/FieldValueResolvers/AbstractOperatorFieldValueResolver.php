<?php
namespace PoP\ComponentModel\FieldValueResolvers;
use PoP\ComponentModel\FieldResolvers\AbstractFieldResolver;

abstract class AbstractOperatorFieldValueResolver extends AbstractDBDataFieldValueResolver
{
    public static function getClassesToAttachTo(): array
    {
        return [
        	AbstractFieldResolver::class,
        ];
    }
}
