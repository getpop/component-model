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
    public function getInterfaceName(): string;
}