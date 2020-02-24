<?php
namespace PoP\ComponentModel\TypeResolverDecorators;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\API\Environment;

abstract class AbstractPublicSchemaTypeResolverDecorator extends AbstractTypeResolverDecorator
{
    /**
     * Enable only for public schema
     *
     * @param TypeResolverInterface $typeResolver
     * @return array
     */
    public function enabled(TypeResolverInterface $typeResolver): bool
    {
        return !Environment::usePrivateSchemaMode();
    }
}
