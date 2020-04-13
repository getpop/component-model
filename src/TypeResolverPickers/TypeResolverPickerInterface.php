<?php
namespace PoP\ComponentModel\TypeResolverPickers;

interface TypeResolverPickerInterface
{
    public function getTypeResolverClass(): string;
    public function isIDOfType($resultItemID): bool;
    public function isInstanceOfType($object): bool;
}
