<?php
namespace PoP\ComponentModel\TypeResolverPickers;

interface TypeResolverPickerInterface
{
	public function getTypeResolverClass(): string;
    public function process($resultItemOrID): bool;
    public function cast($resultItem);
}
