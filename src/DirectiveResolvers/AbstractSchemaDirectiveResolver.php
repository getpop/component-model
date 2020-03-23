<?php
namespace PoP\ComponentModel\DirectiveResolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\Schema\WithVersionConstraintFieldOrDirectiveResolverTrait;

abstract class AbstractSchemaDirectiveResolver extends AbstractDirectiveResolver implements SchemaDirectiveResolverInterface
{
    use WithVersionConstraintFieldOrDirectiveResolverTrait;

    public function getSchemaDefinitionResolver(TypeResolverInterface $typeResolver): ?SchemaDirectiveResolverInterface
    {
        return $this;
    }
    public function getSchemaDirectiveDescription(TypeResolverInterface $typeResolver): ?string
    {
        return null;
    }
    public function getSchemaDirectiveWarningDescription(TypeResolverInterface $typeResolver): ?string
    {
        return null;
    }
    public function getSchemaDirectiveDeprecationDescription(TypeResolverInterface $typeResolver): ?string
    {
        return null;
    }
    public function getSchemaDirectiveExpressions(TypeResolverInterface $typeResolver): array
    {
        return [];
    }
    public function getSchemaDirectiveArgs(TypeResolverInterface $typeResolver): array
    {
        return [];
    }
    public function getFilteredSchemaDirectiveArgs(TypeResolverInterface $typeResolver): array
    {
        $schemaDirectiveArgs = $this->getSchemaDirectiveArgs($typeResolver);
        $this->maybeAddVersionConstraintSchemaFieldOrDirectiveArg($schemaDirectiveArgs);
        return $schemaDirectiveArgs;
    }
    public function getSchemaDirectiveVersion(TypeResolverInterface $typeResolver): ?string
    {
        return null;
    }
    public function enableOrderedSchemaDirectiveArgs(TypeResolverInterface $typeResolver): bool
    {
        return true;
    }
    public function isGlobal(TypeResolverInterface $typeResolver): bool
    {
        return false;
    }
}
