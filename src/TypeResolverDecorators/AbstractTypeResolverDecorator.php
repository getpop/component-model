<?php
namespace PoP\ComponentModel\TypeResolverDecorators;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;
use PoP\ComponentModel\TypeResolverDecorators\TypeResolverDecoratorInterface;

abstract class AbstractTypeResolverDecorator implements TypeResolverDecoratorInterface
{
    /**
     * This class is attached to a TypeResolver
     */
    use AttachableExtensionTrait;

    /**
     * Return an array of fieldNames as keys, and, for each fieldName, an array of directives (including directive arguments) to be applied always on the field
     *
     * @param TypeResolverInterface $typeResolver
     * @return array
     */
    public function getMandatoryDirectivesForFields(TypeResolverInterface $typeResolver): array
    {
        return [];
    }

    /**
     * Return an array of directiveName as keys, and, for each directiveName, an array of directives (including directive arguments) to be applied before
     *
     * @param TypeResolverInterface $typeResolver
     * @return array
     */
    public function getMandatoryDirectivesForDirectives(TypeResolverInterface $typeResolver): array
    {
        return [];
    }
}
