<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\Engine_Vars;
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

    final public function getNamespacedInterfaceName(): string
    {
        $namespace = $this->getNamespace();
        return ($namespace ? $namespace.SchemaDefinition::TOKEN_NAMESPACE_SEPARATOR : '').$this->getInterfaceName();
    }

    final public function getMaybeNamespacedInterfaceName(): string
    {
        $vars = Engine_Vars::getVars();
        return $vars['namespace-types-and-interfaces'] ?
            $this->getNamespacedInterfaceName() :
            $this->getInterfaceName();
    }

    public function getSchemaInterfaceDescription(): ?string
    {
        return null;
    }

    public function getSchemaInterfaceVersion(string $fieldName): ?string
    {
        return null;
    }
}
