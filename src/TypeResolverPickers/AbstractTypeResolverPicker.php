<?php
namespace PoP\ComponentModel\TypeResolverPickers;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;

abstract class AbstractTypeResolverPicker
{
	use AttachableExtensionTrait;

    abstract public function getTypeResolverClass(): string;

    public function process($resultItemOrID): bool
    {
        return false;
    }

    public function cast($resultItem)
    {
        return $resultItem;
    }
}
