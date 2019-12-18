<?php
namespace PoP\ComponentModel\TypeResolvers;

interface UnionTypeResolverInterface
{
    // public function addTypeToID($resultItemID): string;
    public function getTypeResolverClassForResultItem($resultItemID);
    public function getTypeResolverAndPicker($resultItem): ?array;
}
