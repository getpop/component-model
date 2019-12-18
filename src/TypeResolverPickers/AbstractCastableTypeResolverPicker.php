<?php
namespace PoP\ComponentModel\TypeResolverPickers;

use PoP\ComponentModel\TypeResolverPickers\AbstractTypeResolverPicker;
use PoP\ComponentModel\TypeResolverPickers\CastableTypeResolverPickerInterface;

abstract class AbstractCastableTypeResolverPicker extends AbstractTypeResolverPicker implements CastableTypeResolverPickerInterface
{
	public function cast($resultItem)
    {
        return $resultItem;
    }
}
