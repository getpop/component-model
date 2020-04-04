<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\State\ApplicationState;
use PoP\ComponentModel\Schema\SchemaHelpers;
use PoP\ComponentModel\Schema\SchemaDefinition;
use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\FieldResolvers\SchemaDefinitionResolverTrait;

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
        $vars = ApplicationState::getVars();
        return $vars['namespace-types-and-interfaces'] ?
            $this->getNamespacedInterfaceName() :
            $this->getInterfaceName();
    }

    public function getSchemaInterfaceDescription(): ?string
    {
        return null;
    }

    /**
     * The fieldResolver will determine if it has a version or not, however the signature
     * of the fields comes from the interface. Only if there's a version will fieldArg "versionConstraint"
     * be added to the field. Hence, the interface must always say it has a version.
     * This will make fieldArg "versionConstraint" be always added to fields implementing an interface,
     * even if they do not have a version. However, the other way around, to say `false`,
     * would not allow any field implementing an interface to be versioned. So this way is better.
     *
     * @param TypeResolverInterface $typeResolver
     * @param string $fieldName
     * @return boolean
     */
    protected function hasSchemaFieldVersion(TypeResolverInterface $typeResolver, string $fieldName): bool
    {
        return true;
    }

    // public function getSchemaInterfaceVersion(string $fieldName): ?string
    // {
    //     return null;
    // }
}
