<?php
namespace PoP\ComponentModel\FieldResolvers;

use PoP\ComponentModel\FieldResolvers\FieldSchemaDefinitionResolverInterface;

interface FieldInterfaceResolverInterface extends FieldSchemaDefinitionResolverInterface
{
    /**
     * Get an array with the fieldNames that the fieldResolver must implement
     *
     * @return array
     */
    public static function getFieldNamesToImplement(): array;
    /**
     * An interface can itself implement other interfaces!
     *
     * @return array
     */
    public static function getImplementedInterfaceClasses(): array;
    public function getInterfaceName(): string;
    public function getNamespace(): string;
    public function getNamespacedInterfaceName(): string;
    public function getMaybeNamespacedInterfaceName(): string;
    public function getSchemaInterfaceDescription(): ?string;
    public function getSchemaInterfaceVersion(string $fieldName): ?string;
}
