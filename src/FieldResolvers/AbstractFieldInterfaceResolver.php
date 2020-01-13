<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\FieldResolvers\SchemaDefinitionResolverTrait;

abstract class AbstractFieldInterfaceResolver implements FieldInterfaceResolverInterface
{
    use SchemaDefinitionResolverTrait;

    public static function getFieldNamesToResolve(): array
    {
        return self::getFieldNamesToImplement();
    }

    public function getSchemaInterfaceDescription(): ?string
    {
        return null;
    }

    public function getInterfaceSchemaKey(array $options = []): string
    {
        // Use this class as the key
        if ($options['use-type-resolver-class-as-schema-key']) {
            return get_called_class();
        }
        // By default, use the type name
        return $this->getInterfaceName();
    }
}
