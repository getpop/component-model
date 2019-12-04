<?php
namespace PoP\ComponentModel\TypeResolvers;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;

abstract class AbstractConvertibleTypeResolverPicker
{
	use AttachableExtensionTrait;

    abstract public function getTypeResolverClass(): string;

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
