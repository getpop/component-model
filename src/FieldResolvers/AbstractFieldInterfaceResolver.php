<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\Environment;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\FieldResolvers\SchemaDefinitionResolverTrait;
use PoP\ComponentModel\Schema\SchemaHelpers;

abstract class AbstractFieldInterfaceResolver implements FieldInterfaceResolverInterface
{
    use SchemaDefinitionResolverTrait;

    public static function getFieldNamesToResolve(): array
    {
        return self::getFieldNamesToImplement();
    }

    public static function getImplementedInterfaceClasses(): array
    {
        return [];
    }

    public function getNamespace(): string
    {
        return SchemaHelpers::convertNamespace(SchemaHelpers::getOwnerAndProjectFromNamespace(__NAMESPACE__));
    }

    final public function getQualifiedInterfaceName(): string
    {
        $namespace = $this->getNamespace();
        return ($namespace ? $namespace.SchemaDefinition::TOKEN_NAMESPACE_SEPARATOR : '').$this->getInterfaceName();
    }

    final public function getMaybeQualifiedInterfaceName(): string
    {
        return Environment::namespaceTypesAndInterfaces() ?
            $this->getQualifiedInterfaceName() :
            $this->getInterfaceName();
    }

    public function getSchemaInterfaceDescription(): ?string
    {
        return null;
    }
}
