<?php
namespace PoP\ComponentModel\FieldResolvers;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;

abstract class AbstractConvertibleFieldResolverPicker
{
	use AttachableExtensionTrait;

    abstract public function getFieldResolverClass(): string;

    abstract public function getSchemaDefinitionObjectNature(): string;

    public function process($resultItem): bool
    {
        return false;
    }

    public function cast($resultItem)
    {
        return $resultItem;
    }
}
