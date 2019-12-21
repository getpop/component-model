<?php
namespace PoP\ComponentModel\TypeResolvers;

use PoP\ComponentModel\TypeResolvers\TypeResolverInterface;
use PoP\ComponentModel\TypeResolverPickers\TypeResolverPickerInterface;

interface UnionTypeResolverInterface
{
    // public function addTypeToID($resultItemID): string;
    public function getTypeResolverClassForResultItem($resultItemID);
    public function getTargetTypeResolverPicker($resultItem): ?TypeResolverPickerInterface;
    public function getTargetTypeResolver($resultItem): ?TypeResolverInterface;
    public function getResultItemIDTargetTypeResolvers(array $ids): array;
    public function getTargetTypeResolverClasses(): array;
}
