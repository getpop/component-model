<?php
namespace PoP\ComponentModel\TypeResolverPickers;
use PoP\ComponentModel\AttachableExtensions\AttachableExtensionTrait;

abstract class AbstractTypeResolverPicker implements TypeResolverPickerInterface
{
	use AttachableExtensionTrait;

    public function cast($resultItem)
    {
        return $resultItem;
    }
}
